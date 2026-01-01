<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Models\UserSettings;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SettingsResource extends Resource
{
    protected static ?string $model = UserSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    public static function getNavigationUrl(): string
    {
        // Get the user's settings ID (create if doesn't exist) for navigation
        if (auth()->check()) {
            $settings = auth()->user()->getOrCreateSettings();
            return static::getUrl('index', ['record' => $settings->id]);
        }

        // Fallback for unauthenticated contexts
        return static::getUrl('index', ['record' => '1']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proxy Settings')
                    ->description('Configure proxy usage for Meta API requests.')
                    ->schema([
                        Toggle::make('proxy_enabled')
                            ->label('Enable Proxies')
                            ->helperText('Use proxies when making Meta API requests.')
                            ->default(false)
                            ->live(),

                        Select::make('proxy_rotation_type')
                            ->label('Proxy Rotation Type')
                            ->options([
                                'round-robin' => 'Round Robin - Cycle through proxies sequentially',
                                'random' => 'Random - Randomly select a proxy',
                                'sequential' => 'Sequential - Use least recently used proxy',
                            ])
                            ->native(false)
                            ->required()
                            ->default('round-robin')
                            ->visible(fn ($get) => $get('proxy_enabled')),
                    ]),

                Section::make('Notification Settings')
                    ->description('Configure Telegram notification preferences.')
                    ->schema([
                        Toggle::make('telegram_notifications_enabled')
                            ->label('Enable Telegram Notifications')
                            ->helperText('Send notifications to configured Telegram bots.')
                            ->default(true),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSettings::route('/{record}/edit'),
        ];
    }
}
