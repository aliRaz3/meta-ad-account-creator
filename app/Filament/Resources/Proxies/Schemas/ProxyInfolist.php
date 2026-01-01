<?php

namespace App\Filament\Resources\Proxies\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProxyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proxy Information')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Proxy Name')
                            ->placeholder('Unnamed Proxy'),

                        TextEntry::make('protocol')
                            ->label('Protocol')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'http' => 'info',
                                'https' => 'success',
                                'socks4' => 'warning',
                                'socks5' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('host')
                            ->label('Host')
                            ->copyable()
                            ->copyMessage('Host copied'),

                        TextEntry::make('port')
                            ->label('Port'),

                        TextEntry::make('username')
                            ->label('Username')
                            ->placeholder('No authentication'),

                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        IconEntry::make('is_validated')
                            ->label('Validated')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-badge')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('gray'),
                    ])->columns(2),

                Section::make('Statistics')
                    ->schema([
                        TextEntry::make('success_count')
                            ->label('Success Count')
                            ->badge()
                            ->color('success'),

                        TextEntry::make('failure_count')
                            ->label('Failure Count')
                            ->badge()
                            ->color('danger'),

                        TextEntry::make('last_used_at')
                            ->label('Last Used')
                            ->dateTime()
                            ->placeholder('Never used'),

                        TextEntry::make('last_validated_at')
                            ->label('Last Validated')
                            ->dateTime()
                            ->placeholder('Never validated'),

                        TextEntry::make('last_error')
                            ->label('Last Error')
                            ->placeholder('No errors')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
