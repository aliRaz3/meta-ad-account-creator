<?php

namespace App\Filament\Resources\Proxies\Tables;

use App\Services\ProxyService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ProxiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->default('Unnamed Proxy'),

                TextColumn::make('protocol')
                    ->label('Protocol')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'https', 'socks5' => 'success',
                        'http', 'socks4' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('host')
                    ->label('Host')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Host copied'),

                TextColumn::make('port')
                    ->label('Port')
                    ->sortable(),

                IconColumn::make('is_validated')
                    ->label('Validated')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-question-mark-circle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                TextColumn::make('success_count')
                    ->label('Success')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('failure_count')
                    ->label('Failures')
                    ->sortable()
                    ->badge()
                    ->color('danger'),

                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never')
                    ->since(),

                TextColumn::make('last_error')
                    ->label('Last Error')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    })
                    ->placeholder('None')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All proxies')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                TernaryFilter::make('is_validated')
                    ->label('Validated')
                    ->placeholder('All proxies')
                    ->trueLabel('Validated only')
                    ->falseLabel('Not validated'),

                SelectFilter::make('protocol')
                    ->options([
                        'http' => 'HTTP',
                        'https' => 'HTTPS',
                        'socks4' => 'SOCKS4',
                        'socks5' => 'SOCKS5',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('validate')
                        ->label('Validate')
                        ->icon('heroicon-o-shield-check')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Validate Proxy')
                        ->modalDescription('This will test the proxy connection.')
                        ->action(function ($record) {
                            try {
                                $result = $record->validate();

                                if ($result) {
                                    Notification::make()
                                        ->title('Proxy validated successfully')
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Proxy validation failed')
                                        ->body($record->last_error)
                                        ->danger()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Validation error')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->headerActions([
                Action::make('bulk_import')
                    ->label('Bulk Import')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->form([
                        Textarea::make('bulk_proxies')
                            ->label('Proxy URLs')
                            ->placeholder("http://username:password@host:port\nhttps://host:port\nsocks5://user:pass@host:port")
                            ->helperText('Enter one proxy URL per line. Supports: http://, https://, socks4://, socks5://')
                            ->rows(10)
                            ->required(),
                    ])
                    ->action(function (array $data, ProxyService $proxyService) {
                        try {
                            $count = $proxyService->createBulkProxies(
                                Auth::user(),
                                $data['bulk_proxies']
                            );

                            Notification::make()
                                ->title("Successfully imported {$count} proxies")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Import failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('validate_all')
                    ->label('Validate All')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Validate All Proxies')
                    ->modalDescription('This will test all proxies. It may take a while.')
                    ->action(function (ProxyService $proxyService) {
                        try {
                            $results = $proxyService->validateAllProxies(Auth::user());

                            Notification::make()
                                ->title('Validation complete')
                                ->body("Validated: {$results['validated']}, Failed: {$results['failed']}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Validation error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('validate')
                        ->label('Validate Selected')
                        ->icon('heroicon-o-shield-check')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $validated = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if ($record->validate()) {
                                    $validated++;
                                } else {
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title('Validation complete')
                                ->body("Validated: {$validated}, Failed: {$failed}")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
