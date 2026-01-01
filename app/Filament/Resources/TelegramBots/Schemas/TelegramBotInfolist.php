<?php

namespace App\Filament\Resources\TelegramBots\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TelegramBotInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bot Information')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Bot Name')
                            ->placeholder('Unnamed Bot'),

                        TextEntry::make('chat_id')
                            ->label('Chat ID')
                            ->copyable()
                            ->copyMessage('Chat ID copied')
                            ->copyMessageDuration(1500),

                        IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        TextEntry::make('last_notification_at')
                            ->label('Last Notification')
                            ->dateTime()
                            ->placeholder('Never'),
                    ])->columns(2),

                Section::make('Notification Preferences')
                    ->schema([
                        TextEntry::make('notification_preferences')
                            ->label('Subscribed Events')
                            ->formatStateUsing(fn($state) => TelegramBotForm::getNotificationEvents()[$state] ?? 'Unknown')
                            ->placeholder('No events selected')
                            ->badge()
                            ->color('info'),
                    ]),
            ]);
    }
}
