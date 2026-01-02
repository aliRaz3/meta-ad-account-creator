<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdAccountResource\Pages;
use App\Models\AdAccount;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AdAccountResource extends Resource
{
    protected static ?string $model = AdAccount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Ad Accounts';

    protected static ?string $modelLabel = 'Ad Account';

    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ad Account Information')
                    ->components([
                        TextEntry::make('name')
                            ->label('Account Name'),

                        TextEntry::make('ad_account_id')
                            ->label('Meta Ad Account ID')
                            ->copyable()
                            ->placeholder('Not created yet'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'Pending' => 'gray',
                                'Created' => 'success',
                                'Failed' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('bmAccount.title')
                            ->label('BM Account'),

                        TextEntry::make('bmJob.id')
                            ->label('Job ID')
                            ->url(fn(AdAccount $record): string => route('filament.admin.resources.bm-jobs.view', ['record' => $record->bm_job_id])),

                        TextEntry::make('currency')
                            ->label('Currency'),

                        TextEntry::make('time_zone')
                            ->label('Time Zone')
                            ->formatStateUsing(function ($state) {
                                $timezones = config('adaccount.timezones', []);
                                return isset($timezones[$state])
                                    ? "{$timezones[$state]['label']} ({$timezones[$state]['offset']})"
                                    : $state;
                            }),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Section::make('API Response')
                    ->components([
                        TextEntry::make('api_response')
                            ->label('')
                            ->state(function (AdAccount $record): string {
                                if (empty($record->api_response)) {
                                    return 'No API response available.';
                                }
                                return json_encode(json_decode($record->api_response), JSON_PRETTY_PRINT);
                            })
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'font-mono text-xs'])
                            ->html()
                            ->formatStateUsing(fn(string $state): string => '<pre class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg overflow-auto max-h-96">' . htmlspecialchars($state) . '</pre>'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    /**
     * @return Table
     */
    public static function table(Table $table): Table
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

                TextColumn::make('bmAccount.title')
                    ->label('BM Account')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('bmJob.id')
                    ->label('Job ID')
                    ->sortable()
                    ->url(fn(AdAccount $record): string => route('filament.admin.resources.bm-jobs.view', ['record' => $record->bm_job_id])),

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

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Created' => 'Created',
                        'Failed' => 'Failed',
                    ]),
                SelectFilter::make('bm_account_id')
                    ->label('BM Account')
                    ->relationship('bmAccount', 'title'),
                TrashedFilter::make()
                    ->visible(fn () => Auth::user()->isAdmin()),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make()
                        ->visible(fn () => Auth::user()->isAdmin()),
                    ForceDeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->isAdmin()),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll(config('adaccount.polling_interval', 5) . 's');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdAccounts::route('/'),
            'view' => Pages\ViewAdAccount::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', Auth::id())
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
