<?php

namespace App\Filament\Resources\TelegramBots\Pages;

use App\Filament\Resources\TelegramBots\TelegramBotResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListTelegramBots extends ListRecords
{
    protected static string $resource = TelegramBotResource::class;

    protected function getHeaderActions(): array
    {
        $settings = Auth::user()->getOrCreateSettings();
        $notificationsEnabled = $settings->telegram_notifications_enabled;

        return [
            Action::make('toggle_notifications')
                ->label($notificationsEnabled ? 'Notifications Enabled' : 'Notifications Disabled')
                ->icon($notificationsEnabled ? 'heroicon-o-bell' : 'heroicon-o-bell-slash')
                ->color($notificationsEnabled ? 'success' : 'gray')
                ->requiresConfirmation()
                ->modalHeading($notificationsEnabled ? 'Disable Telegram Notifications?' : 'Enable Telegram Notifications?')
                ->modalDescription('This will ' . ($notificationsEnabled ? 'stop' : 'allow') . ' all Telegram notifications.')
                ->action(function () use ($settings, $notificationsEnabled) {
                    $settings->update(['telegram_notifications_enabled' => !$notificationsEnabled]);
                    Notification::make()
                        ->title('Notifications ' . (!$notificationsEnabled ? 'enabled' : 'disabled'))
                        ->success()
                        ->send();

                    // Redirect to refresh the page and update button state
                    redirect()->to(TelegramBotResource::getUrl('index'));
                }),
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = Auth::id();
                    return $data;
                }),
        ];
    }
}
