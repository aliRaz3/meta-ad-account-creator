<?php

namespace App\Filament\Actions\AdAccount;

use App\Models\AdAccount;
use App\Services\Meta\AdAccountService;
use App\Services\Meta\BMUpdateService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class AssignUserToAdAccountAction
{
    /**
     * Create action for single record
     */
    public static function make(): Action
    {
        return Action::make('assign_user')
            ->label('Assign Users')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->visible(fn(AdAccount $record): bool => !empty($record->ad_account_id))
            ->schema(fn(AdAccount $record) => static::schema($record))
            ->action(fn(array $data, AdAccount $record) => static::handleSingle($data, $record))
            ->modalHeading('Assign Users to Ad Account')
            ->modalSubmitActionLabel('Assign Users')
            ->modalSubmitAction(fn($action) => $action->color('primary'))
            ->modalWidth('lg');
    }

    /**
     * Create action for bulk records
     */
    public static function makeBulk(): BulkAction
    {
        return BulkAction::make('bulk_assign_user')
            ->label('Assign Users to All')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->schema(fn(Collection $records) => static::schemaBulk($records))
            ->action(fn(array $data, Collection $records) => static::handleBulk($data, $records))
            ->modalHeading('Assign Users to Multiple Ad Accounts')
            ->modalSubmitActionLabel('Assign to All')
            ->modalSubmitAction(fn($action) => $action->color('primary'))
            ->modalWidth('lg');
    }

    /**
     * Schema for single record
     */
    protected static function schema(AdAccount $record): array
    {
        $userOptions = static::fetchUsers($record->bmAccount);

        return [
            Select::make('user_ids')
                ->label('Select Users')
                ->options($userOptions)
                ->searchable()
                ->multiple()
                ->required()
                ->helperText('Select one or more users from the Business Manager'),

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

    /**
     * Schema for bulk records
     */
    protected static function schemaBulk(Collection $records): array
    {
        // Check if all records have the same BM ID
        $bmIds = $records->pluck('bm_account_id')->unique();

        if ($bmIds->count() > 1) {
            return [
                Section::make()
                    ->schema([
                        TextInput::make('error')
                            ->label('Error')
                            ->default('All selected ad accounts must belong to the same Business Manager')
                            ->disabled(),
                    ]),
            ];
        }

        $firstRecord = $records->first();
        $userOptions = static::fetchUsers($firstRecord->bmAccount);

        return [
            Select::make('user_ids')
                ->label('Select Users')
                ->options($userOptions)
                ->searchable()
                ->multiple()
                ->required()
                ->helperText('These users will be assigned to all selected ad accounts'),

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

    /**
     * Fetch users from Business Manager
     */
    protected static function fetchUsers($bmAccount): array
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

    /**
     * Handle single record assignment
     */
    protected static function handleSingle(array $data, AdAccount $record): void
    {
        try {
            $bmAccount = $record->bmAccount;
            $accessToken = $bmAccount->access_token;
            $service = new AdAccountService();
            $userIds = $data['user_ids'];
            
            $successCount = 0;
            $failCount = 0;
            $errors = [];

            foreach ($userIds as $userId) {
                $result = $service->assignUserToAdAccount(
                    $record->ad_account_id,
                    $accessToken,
                    $userId,
                    $data['tasks']
                );

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                    $errors[] = "User {$userId}: " . ($result['error'] ?? 'Unknown error');
                }
            }

            if ($successCount === count($userIds)) {
                Notification::make()
                    ->title('All Users Assigned Successfully')
                    ->success()
                    ->body("{$successCount} user(s) assigned to the ad account")
                    ->send();
            } elseif ($successCount > 0) {
                $body = "Assigned: {$successCount} | Failed: {$failCount}";
                if (!empty($errors)) {
                    $body .= "\n\nErrors:\n" . implode("\n", array_slice($errors, 0, 3));
                }
                Notification::make()
                    ->title('Partial Assignment Completed')
                    ->warning()
                    ->body($body)
                    ->send();
            } else {
                $body = "Failed to assign all {$failCount} user(s)";
                if (!empty($errors)) {
                    $body .= "\n\nErrors:\n" . implode("\n", array_slice($errors, 0, 3));
                }
                Notification::make()
                    ->title('Assignment Failed')
                    ->danger()
                    ->body($body)
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Failed to assign users to ad account', [
                'ad_account_id' => $record->ad_account_id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Error')
                ->danger()
                ->body('An error occurred: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Handle bulk records assignment
     */
    protected static function handleBulk(array $data, Collection $records): void
    {
        // Check if all records have the same BM ID
        $bmIds = $records->pluck('bm_account_id')->unique();

        if ($bmIds->count() > 1) {
            Notification::make()
                ->title('Different Business Managers')
                ->warning()
                ->body('All selected ad accounts must belong to the same Business Manager')
                ->send();
            return;
        }

        if (isset($data['error'])) {
            return;
        }

        // Get valid ad account IDs
        $adAccountIds = $records->pluck('ad_account_id')->filter()->values()->toArray();
        $skipped = $records->count() - count($adAccountIds);

        if (empty($adAccountIds)) {
            Notification::make()
                ->title('No Valid Ad Accounts')
                ->warning()
                ->body('No ad accounts with valid Meta IDs found')
                ->send();
            return;
        }

        $firstRecord = $records->first();
        $bmAccount = $firstRecord->bmAccount;
        $accessToken = $bmAccount->access_token;
        $service = new AdAccountService();
        $userIds = $data['user_ids'];
        $userCount = count($userIds);

        Log::info('Starting bulk assignment for multiple users', [
            'total_accounts' => count($adAccountIds),
            'user_count' => $userCount,
        ]);

        // Process each user using batch API
        $totalAssigned = 0;
        $totalFailed = 0;
        $allErrors = [];

        foreach ($userIds as $userId) {
            $batchResult = $service->assignUserToAdAccountsBatch(
                $adAccountIds,
                $accessToken,
                $userId,
                $data['tasks']
            );

            $summary = $batchResult['summary'];
            $totalAssigned += $summary['success'];
            $totalFailed += $summary['failed'];

            // Collect errors
            foreach ($batchResult['results'] as $result) {
                if (!$result['success']) {
                    $allErrors[] = "User {$userId} → " . $result['ad_account_id'] . ": " . ($result['error'] ?? 'Unknown error');
                }
            }
        }

        // Calculate statistics
        $expectedTotal = count($adAccountIds) * $userCount;
        $successRate = $expectedTotal > 0 ? round(($totalAssigned / $expectedTotal) * 100) : 0;

        // Build notification
        $stats = "Users: {$userCount} | Accounts: " . count($adAccountIds) . " | Assigned: {$totalAssigned}/{$expectedTotal} ({$successRate}%)";
        if ($skipped > 0) {
            $stats .= " | Skipped: {$skipped}";
        }

        if ($totalAssigned === $expectedTotal) {
            $title = 'All Users Assigned Successfully';
            $type = 'success';
        } elseif ($totalAssigned > 0) {
            $title = 'Partial Assignment Completed';
            $type = 'warning';
        } else {
            $title = 'Assignment Failed';
            $type = 'danger';
        }

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

        Log::info('Bulk assignment for multiple users completed', [
            'user_count' => $userCount,
            'total_accounts' => count($adAccountIds),
            'total_assigned' => $totalAssigned,
            'total_failed' => $totalFailed,
            'success_rate' => $successRate,
        ]);
    }
}
