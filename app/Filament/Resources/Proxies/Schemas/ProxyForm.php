<?php

namespace App\Filament\Resources\Proxies\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProxyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proxy Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Proxy Name')
                            ->placeholder('My Proxy Server')
                            ->helperText('A friendly name to identify this proxy.')
                            ->maxLength(255),

                        Select::make('protocol')
                            ->label('Protocol')
                            ->options([
                                'http' => 'HTTP',
                                'https' => 'HTTPS',
                                'socks4' => 'SOCKS4',
                                'socks5' => 'SOCKS5',
                            ])
                            ->required()
                            ->default('http')
                            ->native(false),

                        TextInput::make('host')
                            ->label('Host')
                            ->required()
                            ->placeholder('proxy.example.com')
                            ->maxLength(255),

                        TextInput::make('port')
                            ->label('Port')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(8080),
                    ])->columns(2),

                Section::make('Authentication')
                    ->description('Leave empty if proxy doesn\'t require authentication.')
                    ->schema([
                        TextInput::make('username')
                            ->label('Username')
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Enable or disable this proxy.')
                            ->default(true),
                    ]),
            ]);
    }
}
