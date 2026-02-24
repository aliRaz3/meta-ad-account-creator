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
            ->label('Assign User')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->visible(fn(AdAccount $record): bool => !empty($record->ad_account_id))
            ->schema(fn(AdAccount $record) => static::schema($record))
            ->action(fn(array $data, AdAccount $record) => static::handleSingle($data, $record))
            ->modalHeading('Assign User to Ad Account')
            ->modalSubmitActionLabel('Assign User')
            ->modalSubmitAction(fn($action) => $action->color('primary'))
            ->modalWidth('lg');
    }

    /**
     * Create action for bulk records
     */
    public static function makeBulk(): BulkAction
    {
        return BulkAction::make('bulk_assign_user')
            ->label('Assign User to All')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->schema(fn(Collection $records) => static::schemaBulk($records))
            ->action(fn(array $data, Collection $records) => static::handleBulk($data, $records))
            ->modalHeading('Assign User to Multiple Ad Accounts')
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
            Select::make('user_id')
                ->label('Select User')
                ->options($userOptions)
                ->searchable()
                ->required()
                ->helperText('Select a user from the Business Manager'),

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
            Select::make('user_id')
                ->label('Select User')
                ->options($userOptions)
                ->searchable()
                ->required()
                ->helperText('This user will be assigned to all selected ad accounts'),

            CheckboxList::make('tasks')
                ->label('Permissions')
                ->options(config('adaccount.ad_account_user_tasks'))
                ->descriptions(config('adaccount.ad_account_user_tasks'))
                ->required()
                ->default(['ANALYZE'])
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
            $result = $service->assignUserToAdAccount(
                $record->ad_account_id,
                $accessToken,
                $data['user_id'],
                $data['tasks']
            );

            if ($result['success']) {
                Notification::make()
                    ->title('User Assigned Successfully')
                    ->success()
                    ->body('User has been assigned to the ad account with selected permissions')
                    ->send();
            } else {
                Notification::make()
                    ->title('Failed to Assign User')
                    ->danger()
                    ->body($result['error'] ?? 'Unknown error occurred')
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Failed to assign user to ad account', [
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

        $successCount = 0;
        $failCount = 0;

        foreach ($records as $record) {
            if (empty($record->ad_account_id)) {
                $failCount++;
                continue;
            }

            try {
                $bmAccount = $record->bmAccount;
                $accessToken = $bmAccount->access_token;

                $service = new AdAccountService();
                $result = $service->assignUserToAdAccount(
                    $record->ad_account_id,
                    $accessToken,
                    $data['user_id'],
                    $data['tasks']
                );

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (Exception $e) {
                Log::error('Bulk assign user failed', [
                    'ad_account_id' => $record->ad_account_id,
                    'error' => $e->getMessage(),
                ]);
                $failCount++;
            }
        }

        Notification::make()
            ->title('Bulk Assignment Complete')
            ->success()
            ->body("Assigned user to {$successCount} ad accounts. Failed: {$failCount}")
            ->send();
    }
}
