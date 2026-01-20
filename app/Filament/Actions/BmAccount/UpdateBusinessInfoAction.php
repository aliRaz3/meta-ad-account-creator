<?php

namespace App\Filament\Actions\BmAccount;

use App\Models\BmAccount;
use App\Services\Meta\BMUpdateService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class UpdateBusinessInfoAction
{
    public static function make(): Action
    {
        return Action::make('update_business_info')
            ->label('Update Business Info')
            ->icon('heroicon-o-building-office-2')
            ->color('info')
            ->modal()
            ->modalWidth('3xl')
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
            $data = $result['data'];
            return [
                'name' => $data['name'] ?? $record->title,
                'vertical' => $data['vertical'] ?? null,
                'timezone_id' => $data['timezone_id'] ?? config('adaccount.default_timezone', 1),
            ];
        }

        return [
            'name' => $record->title,
            'vertical' => null,
            'timezone_id' => config('adaccount.default_timezone', 1),
        ];
    }

    protected static function schema(): array
    {
        return [
            TextInput::make('name')
                ->label('Business Name')
                ->required()
                ->maxLength(255)
                ->helperText('The public name of your business or organization')
                ->columnSpan(12),

            Select::make('vertical')
                ->label('Industry Vertical')
                ->searchable()
                ->options(function () {
                    return collect(config('adaccount.business_verticals', []))
                        ->mapWithKeys(fn($label, $code) => [$code => $label])
                        ->toArray();
                })
                ->helperText('The industry vertical for your business')
                ->columnSpan(12),

            Select::make('timezone_id')
                ->label('Timezone')
                ->required()
                ->searchable()
                ->options(function () {
                    return collect(config('adaccount.timezones', []))
                        ->mapWithKeys(fn($tz, $id) => [$id => "{$tz['label']} ({$tz['offset']})"]);
                })
                ->helperText('Primary timezone for your business')
                ->columnSpan(12),
        ];
    }

    protected static function handle(BmAccount $record, array $data): void
    {
        $bmUpdateService = app(BMUpdateService::class);

        try {
            // Prepare data for API (only include fields that are set)
            $updateData = [];
            if (isset($data['name']) && !empty($data['name'])) {
                $updateData['name'] = $data['name'];
            }
            if (isset($data['vertical']) && !empty($data['vertical'])) {
                $updateData['vertical'] = $data['vertical'];
            }
            if (isset($data['timezone_id']) && !empty($data['timezone_id'])) {
                $updateData['timezone_id'] = $data['timezone_id'];
            }

            $result = $bmUpdateService->updateBusinessInfo(
                $record->business_portfolio_id,
                $record->access_token,
                $updateData
            );

            if ($result['success']) {
                // Update local title if name was changed
                if (isset($data['name'])) {
                    $record->update(['title' => $data['name']]);
                }

                Notification::make()
                    ->title('Business information updated successfully')
                    ->success()
                    ->send();
            } else {
                $errorMessage = $bmUpdateService->formatError($result);
                Notification::make()
                    ->title('Failed to update business information')
                    ->body($errorMessage)
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error updating business information')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
