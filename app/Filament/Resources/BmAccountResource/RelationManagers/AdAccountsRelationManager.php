<?php

namespace App\Filament\Resources\BmAccountResource\RelationManagers;

use App\Filament\Resources\AdAccountResource;
use App\Filament\Resources\BmJobResource;
use App\Models\AdAccount;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'adAccounts';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ad_account_id')
                    ->label('Meta Ad Account ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Copied!')
                    ->placeholder('Not created yet'),

                TextColumn::make('bmJob.id')
                    ->label('Job ID')
                    ->sortable()
                    ->url(fn(AdAccount $record): string => BmJobResource::getUrl('view', ['record' => $record->bm_job_id])),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Pending' => 'gray',
                        'Created' => 'success',
                        'Failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('currency')
                    ->label('Currency')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Created' => 'Created',
                        'Failed' => 'Failed',
                    ]),
                SelectFilter::make('bm_job_id')
                    ->label('Job')
                    ->relationship('bmJob', 'id')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(AdAccount $record): string => AdAccountResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll(config('adaccount.polling_interval', 5) . 's');
    }
}
