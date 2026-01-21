<?php

namespace App\Filament\Actions\BmAccount;

use App\Models\BmAccount;
use App\Services\Meta\BMUpdateService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class UpdateBusinessNameAction
{
    public static function make(): Action
    {
        return Action::make('update_business_name')
            ->label('Update Business Name')
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->modal()
            ->modalWidth('2xl')
            ->modalSubmitAction(fn ($action) => $action->color('primary'))
            ->fillForm(fn (BmAccount $record): array => static::fillForm($record))
            ->schema(static::schema())
            ->action(fn (BmAccount $record, array $data) => static::handle($record, $data));
    }

    protected static function fillForm(BmAccount $record): array
    {
        $bmUpdateService = app(BMUpdateService::class);
        $result = $bmUpdateService->getBusinessInfo(
            $record->business_portfolio_id,
            $record->access_token
        );

        if ($result['success']) {
            return [
                'name' => $result['data']['name'] ?? $record->title,
            ];
        }

        return [
            'name' => $record->title,
        ];
    }

    protected static function schema(): array
    {
        return [
            TextInput::make('name')
                ->label('Business Name')
                ->required()
                ->maxLength(255)
                ->helperText('The name must match the public name of your business or organization')
                ->columnSpan(12),
        ];
    }

    protected static function handle(BmAccount $record, array $data): void
    {
        $bmUpdateService = app(BMUpdateService::class);

        try {
            $result = $bmUpdateService->updateBusinessName(
                $record->business_portfolio_id,
                $record->access_token,
                $data['name']
            );

            if ($result['success']) {
                // Update local title to match
                $record->update(['title' => $data['name']]);

                Notification::make()
                    ->title('Business name updated successfully')
                    ->success()
                    ->send();
            } else {
                $errorMessage = $bmUpdateService->formatError($result);
                Notification::make()
                    ->title('Failed to update business name')
                    ->body($errorMessage)
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error updating business name')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
