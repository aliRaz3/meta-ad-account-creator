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
            ->label('Assign User to All Ad Accounts')
            ->icon('heroicon-o-user-group')
            ->color('success')
            ->requiresConfirmation()
            ->modal()
            ->modalWidth('lg')
            ->modalHeading('Assign User to All Ad Accounts')
            ->modalDescription('Assign a user to all ad accounts in this Business Manager')
            ->modalSubmitActionLabel('Assign to All')
            ->modalSubmitAction(fn($action) => $action->color('primary'))
            ->schema(fn(BmAccount $record) => static::schema($record))
            ->action(fn(array $data, BmAccount $record) => static::handle($data, $record));
    }

    protected static function schema(BmAccount $record): array
    {
        $userOptions = static::fetchUsers($record);

        return [
            Select::make('user_id')
                ->label('Select User')
                ->options($userOptions)
                ->searchable()
                ->required()
                ->helperText('This user will be assigned to all ad accounts in this business'),

            CheckboxList::make('tasks')
                ->label('Permissions')
                ->options(config('adaccount.ad_account_user_tasks'))
                ->descriptions(config('adaccount.ad_account_user_tasks'))
                ->required()
                ->default(['ANALYZE'])
                ->helperText('Select one or more permissions for this user')
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

        Log::info('Starting bulk batch assignment', [
            'bm_account_id' => $record->id,
            'total_accounts' => count($adAccountIds),
            'user_id' => $data['user_id'],
        ]);

        // Use batch API to assign user to all ad accounts
        $batchResult = $service->assignUserToAdAccountsBatch(
            $adAccountIds,
            $accessToken,
            $data['user_id'],
            $data['tasks']
        );

        $summary = $batchResult['summary'];
        $assigned = $summary['success'];
        $failed = $summary['failed'];
        
        // Collect error messages
        $errors = [];
        foreach ($batchResult['results'] as $result) {
            if (!$result['success']) {
                $accountId = $result['ad_account_id'];
                $accountName = $adAccountMap[$accountId]['name'] ?? $accountId;
                $errors[] = "{$accountName}: " . ($result['error'] ?? 'Unknown error');
            }
        }

        // Build notification message
        $stats = "Total: {$total} | Assigned: {$assigned}";
        if ($skipped > 0) {
            $stats .= " | Skipped: {$skipped}";
        }
        if ($failed > 0) {
            $stats .= " | Failed: {$failed}";
        }

        // Determine notification type and title
        if ($assigned === count($adAccountIds)) {
            $title = 'All Ad Accounts Assigned Successfully';
            $type = 'success';
        } elseif ($assigned > 0) {
            $title = 'Partial Assignment Completed';
            $type = 'warning';
        } else {
            $title = 'Assignment Failed';
            $type = 'danger';
        }

        // Add error details if any
        $body = $stats;
        if (!empty($errors)) {
            $errorSample = implode("\n", array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $errorSample .= "\n... and " . (count($errors) - 3) . " more error(s)";
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

        Log::info('Bulk batch assignment completed', [
            'bm_account_id' => $record->id,
            'total' => $total,
            'assigned' => $assigned,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }
}
