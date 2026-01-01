<?php

namespace App\Filament\Resources\TelegramBots\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Get;

class TelegramBotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bot Credentials')
                    ->description('Enter your Telegram bot credentials. Create a bot via @BotFather.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Bot Name')
                            ->placeholder('My Notification Bot')
                            ->helperText('A friendly name to identify this bot.')
                            ->maxLength(255),

                        Toggle::make('update_token')
                            ->label('Update Bot Token')
                            ->helperText('Enable this to update the bot token.')
                            ->default(fn ($record) => $record === null)
                            ->reactive()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record !== null),

                        Placeholder::make('token_status')
                            ->label('Bot Token')
                            ->content('Token is set (enable "Update Bot Token" to change it)')
                            ->visible(fn ($record, $get) => $record !== null && !$get('update_token')),

                        TextInput::make('bot_token')
                            ->label('Bot Token')
                            ->required(fn ($record, $get) => $record === null || $get('update_token'))
                            ->placeholder('123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11')
                            ->helperText('Get this from @BotFather after creating your bot.')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->dehydrated(fn ($record, $get) => $record === null || $get('update_token'))
                            ->visible(fn ($record, $get) => $record === null || $get('update_token')),

                        TextInput::make('chat_id')
                            ->label('Chat ID')
                            ->required()
                            ->placeholder('123456789')
                            ->helperText('Send /start to @userinfobot to get your Chat ID.')
                            ->maxLength(255),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Enable or disable notifications for this bot.')
                            ->default(true),
                    ])->columns(2),

                Section::make('Notification Preferences')
                    ->description('Select which events should trigger notifications.')
                    ->schema([
                        CheckboxList::make('notification_preferences')
                            ->label('Events to Notify')
                            ->options(self::getNotificationEvents())
                            ->descriptions(self::getNotificationDescriptions())
                            ->columns(2)
                            ->gridDirection('row')
                            ->default(array_keys(self::getNotificationEvents())),
                    ]),
            ]);
    }

    public static function getNotificationEvents(): array
    {
        return [
            'job_started' => 'Job Started',
            'job_completed' => 'Job Completed',
            'job_failed' => 'Job Failed',
            'job_paused' => 'Job Paused',
            'job_resumed' => 'Job Resumed',
            'progress_25' => '25% Progress',
            'progress_50' => '50% Progress',
            'progress_75' => '75% Progress',
            'system_errors' => 'System Errors',
        ];
    }

    protected static function getNotificationDescriptions(): array
    {
        return [
            'job_started' => 'When a BM job begins processing',
            'job_completed' => 'When a BM job completes successfully',
            'job_failed' => 'When a BM job encounters an error',
            'job_paused' => 'When a BM job is paused',
            'job_resumed' => 'When a paused job resumes',
            'progress_25' => 'When job reaches 25% completion',
            'progress_50' => 'When job reaches 50% completion',
            'progress_75' => 'When job reaches 75% completion',
            'system_errors' => 'Critical system errors',
        ];
    }
}
