<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('User Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(6),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan(6),

                        Toggle::make('is_admin')
                            ->label('Admin Role')
                            ->helperText('Admins can manage all users and impersonate any user.')
                            ->default(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(12),

                Section::make('Password')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255)
                            ->columnSpan(6)
                            ->helperText(fn (string $operation): string =>
                                $operation === 'edit' ? 'Leave blank to keep current password' : ''
                            ),

                        TextInput::make('password_confirmation')
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->dehydrated(false)
                            ->requiredWith('password')
                            ->maxLength(255)
                            ->columnSpan(6),
                    ])
                    ->columns(12)
                    ->description('Set a strong password for this user.')
                    ->hiddenOn('view'),
            ]);
    }
}
