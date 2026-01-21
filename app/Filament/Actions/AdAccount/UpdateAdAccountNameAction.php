<?php

namespace App\Filament\Actions\AdAccount;

use App\Models\AdAccount;
use App\Services\Meta\AdAccountService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class UpdateAdAccountNameAction
{
    /**
     * Create action for single record
     */
    public static function make(): Action
    {
        return Action::make('update_name')
            ->label('Update Name')
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->visible(fn (AdAccount $record): bool => !empty($record->ad_account_id))
            ->schema(fn (AdAccount $record) => static::schema($record))
            ->action(fn (array $data, AdAccount $record) => static::handleSingle($data, $record))
            ->modalHeading('Update Ad Account Name')
            ->modalSubmitActionLabel('Update Name')
            ->modalSubmitAction(fn ($action) => $action->color('primary'))
            ->modalWidth('md');
    }

    /**
     * Create action for bulk records
     */
    public static function makeBulk(): Action
    {
        return Action::make('bulk_update_name')
            ->label('Update Names')
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->schema(static::schemaBulk())
            ->action(fn (array $data, $records) => static::handleBulk($data, $records))
            ->modalHeading('Bulk Update Ad Account Names')
            ->modalSubmitActionLabel('Update All')
            ->modalSubmitAction(fn ($action) => $action->color('primary'));
    }

    /**
     * Schema for single record
     */
    protected static function schema(AdAccount $record): array
    {
        return [
            TextInput::make('name')
                ->label('Ad Account Name')
                ->default($record->name)
                ->required()
                ->maxLength(255)
                ->helperText('Update the name of this ad account'),
        ];
    }

    /**
     * Schema for bulk records
     */
    protected static function schemaBulk(): array
    {
        return [
            TextInput::make('name_prefix')
                ->label('Name Prefix')
                ->required()
                ->helperText('Prefix for all ad account names. Will be followed by the current name.'),
        ];
    }

    /**
     * Handle single record update
     */
    protected static function handleSingle(array $data, AdAccount $record): void
    {
        try {
            $bmAccount = $record->bmAccount;
            $accessToken = $bmAccount->access_token;

            $service = new AdAccountService();
            $result = $service->updateAdAccountName(
                $record->ad_account_id,
                $accessToken,
                $data['name']
            );

            if ($result['success']) {
                $record->update(['name' => $data['name']]);

                Notification::make()
                    ->title('Name Updated Successfully')
                    ->success()
                    ->body("Ad account name has been updated to: {$data['name']}")
                    ->send();
            } else {
                Notification::make()
                    ->title('Failed to Update Name')
                    ->danger()
                    ->body($result['error'] ?? 'Unknown error occurred')
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Failed to update ad account name', [
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
     * Handle bulk records update
     */
    protected static function handleBulk(array $data, $records): void
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
                $newName = $data['name_prefix'] . ' ' . $record->name;

                $service = new AdAccountService();
                $result = $service->updateAdAccountName(
                    $record->ad_account_id,
                    $accessToken,
                    $newName
                );

                if ($result['success']) {
                    $record->update(['name' => $newName]);
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (Exception $e) {
                Log::error('Bulk update name failed', [
                    'ad_account_id' => $record->ad_account_id,
                    'error' => $e->getMessage(),
                ]);
                $failCount++;
            }
        }

        Notification::make()
            ->title('Bulk Update Complete')
            ->success()
            ->body("Updated {$successCount} ad accounts. Failed: {$failCount}")
            ->send();
    }
}
