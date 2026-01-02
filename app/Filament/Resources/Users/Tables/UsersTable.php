<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use STS\FilamentImpersonate\Actions\Impersonate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),

                TextColumn::make('bm_accounts_count')
                    ->counts('bmAccounts')
                    ->label('BM Accounts')
                    ->sortable(),

                TextColumn::make('bm_jobs_count')
                    ->counts('bmJobs')
                    ->label('BM Jobs')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),

                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_admin')
                    ->label('Role')
                    ->options([
                        true => 'Admin',
                        false => 'Regular User',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->modalWidth('2xl'),
                    Impersonate::make()
                        ->redirectTo(route('filament.admin.pages.dashboard'))
                        // confirmation dialog
                        ->requiresConfirmation()
                        ->modalHeading('Impersonate User')
                        ->modalDescription(fn ($record) => "You are about to impersonate {$record->name}. You can stop impersonating by clicking the 'Leave Impersonation' button in the admin bar."),
                        // ->visible(fn ($record) => $record->id !== Auth::id()),
                    EditAction::make()
                        ->modalWidth('2xl'),
                    Action::make('toggle_admin')
                        ->label(fn ($record) => $record->is_admin ? 'Demote from Admin' : 'Promote to Admin')
                        ->icon(fn ($record) => $record->is_admin ? 'heroicon-o-arrow-down-circle' : 'heroicon-o-arrow-up-circle')
                        ->color(fn ($record) => $record->is_admin ? 'warning' : 'success')
                        ->visible(fn ($record) => $record->id !== Auth::id())
                        ->requiresConfirmation()
                        ->modalHeading(fn ($record) => $record->is_admin ? 'Demote from Admin?' : 'Promote to Admin?')
                        ->modalDescription(fn ($record) => $record->is_admin
                            ? "Remove admin privileges from {$record->name}?"
                            : "Grant admin privileges to {$record->name}?")
                        ->action(function ($record) {
                            $record->update(['is_admin' => !$record->is_admin]);

                            Notification::make()
                                ->title($record->is_admin ? 'User promoted to admin' : 'Admin privileges removed')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('is_admin', 'desc');
    }
}
