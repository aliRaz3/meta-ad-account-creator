<?php

namespace App\Filament\Resources\TelegramBots\Tables;

use App\Services\TelegramNotificationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class TelegramBotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Bot Name')
                    ->searchable()
                    ->sortable()
                    ->default('Unnamed Bot'),

                TextColumn::make('chat_id')
                    ->label('Chat ID')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Chat ID copied')
                    ->copyMessageDuration(1500),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                // show count of subscribed events instead of listing them all
                TextColumn::make('notification_preferences')
                    ->label('Subscribed Events')
                    ->state(function ($record) {
                        $count = is_array($record->notification_preferences) ? count($record->notification_preferences) : 0;
                        return $count . ' event' . ($count !== 1 ? 's' : '');
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('last_notification_at')
                    ->label('Last Notification')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never')
                    ->since(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('test')
                        ->label('Test')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Send Test Notification')
                        ->modalDescription('This will send a test notification to this bot.')
                        ->action(function ($record) {
                            try {
                                $result = (new TelegramNotificationService())->testBot($record);


                                Notification::make()
                                    ->title($result['success'] ? 'Test notification sent' : 'Test notification failed')
                                    ->body($result['message'])
                                    ->status($result['success'] ? 'success' : 'danger')
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Failed to send test notification')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('enable')
                        ->label('Enable Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each->update(['is_active' => true]);
                            Notification::make()
                                ->title('Bots enabled')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('disable')
                        ->label('Disable Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each->update(['is_active' => false]);
                            Notification::make()
                                ->title('Bots disabled')
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
