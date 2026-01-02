<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BmAccountResource\Pages;
use App\Filament\Resources\BmAccountResource\RelationManagers;
use App\Filament\Resources\BmJobResource;
use App\Models\BmAccount;
use App\Models\BmJob;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BmAccountResource extends Resource
{
    protected static ?string $model = BmAccount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'BM Accounts';

    protected static ?string $modelLabel = 'BM Account';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->label('Account Title')
                    ->columnSpan(6)
                    ->helperText('A friendly name for this BM account'),

                TextInput::make('business_portfolio_id')
                    ->required()
                    ->maxLength(255)
                    ->label('Business Portfolio ID')
                    ->columnSpan(6)
                    ->helperText('The Meta Business Portfolio ID')
                    ->unique(ignoreRecord: true),

                Toggle::make('update_access_token')
                    ->label('Update Access Token')
                    ->helperText('Enable to update the access token')
                    ->default(fn($record) => $record === null) // Default ON for create, OFF for edit
                    ->live()
                    ->dehydrated(false)
                    ->columnSpan(12),

                Textarea::make('access_token')
                    ->label('Access Token')
                    ->helperText('Meta API access token (will be encrypted)')
                    ->rows(3)
                    ->columnSpan(12)
                    ->required(fn($get) => $get('update_access_token') === true)
                    ->visible(fn($get) => $get('update_access_token') === true)
                    ->dehydrated(fn($get) => $get('update_access_token') === true)
            ])
            ->columns(12);
    }

    /**
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->label('Title'),

                TextColumn::make('business_portfolio_id')
                    ->searchable()
                    ->sortable()
                    ->label('Business Portfolio ID')
                    ->copyable()
                    ->copyMessage('Copied!')
                    ->copyMessageDuration(1500),

                TextColumn::make('bm_jobs_count')
                    ->counts('bmJobs')
                    ->label('Total Jobs')
                    ->sortable(),

                TextColumn::make('ad_accounts_count')
                    ->counts('adAccounts')
                    ->label('Ad Accounts')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->visible(fn () => Auth::user()->isAdmin()),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('create_job')
                        ->label('Create BM Job')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->modal()
                        ->modalWidth('3xl')
                        ->form([
                            TextInput::make('pattern')
                                ->label('Account Name Pattern')
                                ->required()
                                ->placeholder('TPA-{number}')
                                ->helperText('Use {number} as placeholder for sequential number. Example: TPA-{number}')
                                ->maxLength(255)
                                ->live(onBlur: true),

                            ViewField::make('pattern_preview')
                                ->view('filament.forms.components.pattern-preview')
                                ->visible(fn($get) => filled($get('pattern')))
                                ->viewData(fn($get) => [
                                    'pattern' => $get('pattern'),
                                    'starting' => $get('starting_ad_account_no') ?? 1,
                                    'total' => $get('total_ad_accounts') ?? 1,
                                ]),

                            TextInput::make('starting_ad_account_no')
                                ->label('Starting Number')
                                ->required()
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->helperText('The starting number for the sequence')
                                ->live(onBlur: true),

                            TextInput::make('total_ad_accounts')
                                ->label('Total Ad Accounts')
                                ->required()
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(config('adaccount.max_ad_accounts_per_job', 500))
                                ->helperText('Number of ad accounts to create (max: ' . config('adaccount.max_ad_accounts_per_job', 500) . ')')
                                ->live(onBlur: true),

                            Select::make('currency')
                                ->label('Currency')
                                ->required()
                                ->searchable()
                                ->options(function () {
                                    return collect(config('adaccount.currencies', []))
                                        ->mapWithKeys(fn($label, $code) => [$code => "$code - $label"])
                                        ->toArray();
                                })
                                ->default('USD')
                                ->helperText('Currency for the ad accounts'),

                            Select::make('time_zone')
                                ->label('Time Zone')
                                ->required()
                                ->searchable()
                                ->options(function () {
                                    return collect(config('adaccount.timezones', []))
                                        ->mapWithKeys(fn($tz, $id) => [$id => "{$tz['label']} ({$tz['offset']})"]);
                                })
                                ->default(config('adaccount.default_timezone', 1))
                                ->helperText('Time zone for the ad accounts'),
                        ])
                        ->action(function (BmAccount $record, array $data): void {
                            $bmAccountIds = isset($data['bm_account_id']) ? (array) $data['bm_account_id'] : [$record->id];

                            foreach ($bmAccountIds as $bmAccountId) {
                                BmJob::create([
                                    'bm_account_id' => $bmAccountId,
                                    'user_id' => Auth::id(),
                                    'pattern' => $data['pattern'],
                                    'starting_ad_account_no' => $data['starting_ad_account_no'],
                                    'total_ad_accounts' => $data['total_ad_accounts'],
                                    'currency' => $data['currency'],
                                    'time_zone' => $data['time_zone'],
                                    'status' => 'Pending',
                                    'processed_ad_accounts' => 0,
                                ]);

                                BmJob::dispatchNextPendingJob($bmAccountId);
                            }
                        }),
                    ViewAction::make(),
                    EditAction::make()
                        ->modal()
                        ->modalWidth('2xl'),
                    DeleteAction::make(),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BmJobsRelationManager::class,
            RelationManagers\AdAccountsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBmAccounts::route('/'),
            'view' => Pages\ViewBmAccount::route('/{record}'),
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
}
