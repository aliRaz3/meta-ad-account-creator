<?php

namespace App\Filament\Actions\BmAccount;

use App\Models\BmAccount;
use App\Services\Meta\AdAccountService;
use App\Services\Meta\BMUpdateService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class AssignUserToAllAdAccountsAction
{
    public static function make(): Action
    {
        return Action::make('assign_user_to_all_ad_accounts')
            ->label('Assign Users to All Ad Accounts')
            ->icon('heroicon-o-user-group')
            ->color('success')
            ->requiresConfirmation()
            ->modal()
            ->modalWidth('lg')
            ->modalHeading('Assign Users to All Ad Accounts')
            ->modalDescription('Assign one or more users to all ad accounts in this Business Manager')
            ->modalSubmitActionLabel('Assign to All')
            ->modalSubmitAction(fn($action) => $action->color('primary'))
            ->schema(fn(BmAccount $record) => static::schema($record))
            ->action(fn(array $data, BmAccount $record) => static::handle($data, $record));
    }

    protected static function schema(BmAccount $record): array
    {
        $userOptions = static::fetchUsers($record);

        return [
            Select::make('user_ids')
                ->label('Select Users')
                ->options($userOptions)
                ->searchable()
                ->multiple()
                ->required()
                ->helperText('These users will be assigned to all ad accounts in this business'),

            CheckboxList::make('tasks')
                ->label('Permissions')
                ->options(config('adaccount.ad_account_user_tasks'))
                ->descriptions(config('adaccount.ad_account_user_tasks'))
                ->required()
                ->default(['ANALYZE'])
                ->helperText('Select one or more permissions for all selected users')
                ->columns(1),
        ];
    }

    protected static function fetchUsers(BmAccount $bmAccount): array
    {
        $accessToken = $bmAccount->access_token;
        $businessId = $bmAccount->business_portfolio_id;

        $bmService = new BMUpdateService();

        // Fetch business users and system users
        $businessUsersResult = $bmService->getBusinessUsers($businessId, $accessToken);
        $systemUsersResult = $bmService->getSystemUsers($businessId, $accessToken);

        $userOptions = [];

        if ($businessUsersResult['success']) {
            foreach ($businessUsersResult['data'] as $user) {
                $userOptions[$user['id']] = ($user['name'] ?? 'Unknown') . ' (' . ($user['email'] ?? 'No email') . ') - Business User';
            }
        }

        if ($systemUsersResult['success']) {
            foreach ($systemUsersResult['data'] as $user) {
                $userOptions[$user['id']] = ($user['name'] ?? 'Unknown') . ' - System User';
            }
        }

        return $userOptions;
    }

    protected static function handle(array $data, BmAccount $record): void
    {
        // Collect all ad account IDs and build a mapping
        $adAccounts = $record->adAccounts()
            ->whereNotNull('ad_account_id')
            ->select('id', 'ad_account_id', 'name')
            ->get();

        if ($adAccounts->isEmpty()) {
            Notification::make()
                ->title('No Ad Accounts Found')
                ->warning()
                ->body('This Business Manager has no ad accounts with Meta IDs')
                ->send();
            return;
        }

        $total = $adAccounts->count();
        $accessToken = $record->access_token;
        $service = new AdAccountService();
        $userIds = $data['user_ids'];
        $userCount = count($userIds);

        // Build arrays for batch processing
        $adAccountIds = [];
        $adAccountMap = []; // Map ad_account_id to account details for error reporting

        foreach ($adAccounts as $adAccount) {
            if (!empty($adAccount->ad_account_id)) {
                $adAccountIds[] = $adAccount->ad_account_id;
                $adAccountMap[$adAccount->ad_account_id] = [
                    'id' => $adAccount->id,
                    'name' => $adAccount->name,
                ];
            }
        }

        $skipped = $total - count($adAccountIds);

        if (empty($adAccountIds)) {
            Notification::make()
                ->title('No Valid Ad Accounts')
                ->warning()
                ->body('No ad accounts with valid Meta IDs found')
                ->send();
            return;
        }

        Log::info('Starting bulk batch assignment for multiple users', [
            'bm_account_id' => $record->id,
            'total_accounts' => count($adAccountIds),
            'user_count' => $userCount,
            'user_ids' => $userIds,
        ]);

        // Process each user and collect results
        $totalAssigned = 0;
        $totalFailed = 0;
        $allErrors = [];

        foreach ($userIds as $userId) {
            Log::info('Assigning user to ad accounts', [
                'user_id' => $userId,
                'total_accounts' => count($adAccountIds),
            ]);

            // Use batch API to assign user to all ad accounts
            $batchResult = $service->assignUserToAdAccountsBatch(
                $adAccountIds,
                $accessToken,
                $userId,
                $data['tasks']
            );

            $summary = $batchResult['summary'];
            $totalAssigned += $summary['success'];
            $totalFailed += $summary['failed'];
            
            // Collect error messages for this user
            foreach ($batchResult['results'] as $result) {
                if (!$result['success']) {
                    $accountId = $result['ad_account_id'];
                    $accountName = $adAccountMap[$accountId]['name'] ?? $accountId;
                    $allErrors[] = "User {$userId} → {$accountName}: " . ($result['error'] ?? 'Unknown error');
                }
            }
        }

        // Calculate average success rate per user
        $expectedTotal = count($adAccountIds) * $userCount;
        $successRate = $expectedTotal > 0 ? round(($totalAssigned / $expectedTotal) * 100) : 0;

        // Build notification message
        $stats = "Users: {$userCount} | Accounts: " . count($adAccountIds) . " | Total Assignments: {$totalAssigned}/{$expectedTotal} ({$successRate}%)";
        if ($skipped > 0) {
            $stats .= " | Skipped Accounts: {$skipped}";
        }
        if ($totalFailed > 0) {
            $stats .= " | Failed: {$totalFailed}";
        }

        // Determine notification type and title
        if ($totalAssigned === $expectedTotal) {
            $title = 'All Users Assigned to All Ad Accounts Successfully';
            $type = 'success';
        } elseif ($totalAssigned > 0) {
            $title = 'Partial Assignment Completed';
            $type = 'warning';
        } else {
            $title = 'Assignment Failed';
            $type = 'danger';
        }

        // Add error details if any
        $body = $stats;
        if (!empty($allErrors)) {
            $errorSample = implode("\n", array_slice($allErrors, 0, 5));
            if (count($allErrors) > 5) {
                $errorSample .= "\n... and " . (count($allErrors) - 5) . " more error(s)";
            }
            $body .= "\n\nErrors:\n{$errorSample}";
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body);

        match ($type) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'danger' => $notification->danger(),
        };

        $notification->send();

        Log::info('Bulk batch assignment completed for multiple users', [
            'bm_account_id' => $record->id,
            'user_count' => $userCount,
            'total_accounts' => count($adAccountIds),
            'skipped' => $skipped,
            'total_assigned' => $totalAssigned,
            'total_failed' => $totalFailed,
            'success_rate' => $successRate,
        ]);
    }
}
