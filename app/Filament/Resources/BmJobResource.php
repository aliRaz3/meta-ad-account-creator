<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BmJobResource\Pages;
use App\Jobs\ProcessBmJob;
use App\Models\BmAccount;
use App\Models\BmJob;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BmJobResource extends Resource
{
    protected static ?string $model = BmJob::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'BM Jobs';

    protected static ?string $modelLabel = 'BM Job';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        $currencies = collect(config('adaccount.currencies', []))
            ->mapWithKeys(fn($label, $code) => [$code => "$code - $label"])
            ->toArray();

        return $schema
            ->components([
                Select::make('bm_account_id')
                    ->label('BM Account')
                    ->required()
                    ->searchable()
                    ->options(function () {
                        return BmAccount::where('user_id', Auth::id())
                            ->pluck('title', 'id');
                    })
                    ->helperText('Select the BM account to create ad accounts for'),

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
                    ->options($currencies)
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
            ]);
    }

    /**
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('bmAccount.title')
                    ->label('BM Account')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pattern')
                    ->label('Pattern')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->state(function (BmJob $record): string {
                        $percentage = $record->total_ad_accounts > 0
                            ? round(($record->processed_ad_accounts / $record->total_ad_accounts) * 100, 1)
                            : 0;
                        return "{$percentage}% ({$record->processed_ad_accounts}/{$record->total_ad_accounts})";
                    })
                    ->badge()
                    ->color(function (BmJob $record): string {
                        $percentage = $record->total_ad_accounts > 0
                            ? ($record->processed_ad_accounts / $record->total_ad_accounts) * 100
                            : 0;
                        if ($percentage >= 100) return 'success';
                        if ($percentage >= 50) return 'warning';
                        return 'gray';
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Pending' => 'gray',
                        'Processing' => 'info',
                        'Paused' => 'warning',
                        'Completed' => 'success',
                        'Failed' => 'danger',
                        default => 'gray',
                    }),

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
                        'Processing' => 'Processing',
                        'Paused' => 'Paused',
                        'Completed' => 'Completed',
                        'Failed' => 'Failed',
                    ]),
                SelectFilter::make('bm_account_id')
                    ->label('BM Account')
                    ->relationship('bmAccount', 'title')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('view_details')
                        ->label('View Details')
                        ->icon('heroicon-o-eye')
                        ->url(fn(BmJob $record): string => BmJobResource::getUrl('view', ['record' => $record])),

                    Action::make('pause')
                        ->label('Pause')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->visible(fn(BmJob $record): bool => $record->status === 'Processing')
                        ->requiresConfirmation()
                        ->action(function (BmJob $record) {
                            $record->update(['status' => 'Paused']);
                            // Next job will be dispatched by ProcessBmJob when it detects pause
                        }),

                    Action::make('resume')
                        ->label('Resume')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->visible(fn(BmJob $record): bool => $record->status === 'Paused')
                        ->requiresConfirmation()
                        ->action(function (BmJob $record) {
                            $record->update(['status' => 'Pending']);
                            // Try to dispatch if no other job is processing for this BM Account
                            if (!$record->dispatchIfAvailable()) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Job Queued')
                                    ->body('Another job is currently processing for this BM Account. This job will start when it finishes.')
                                    ->send();
                            }
                        }),

                    Action::make('retry')
                        ->label('Retry')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn(BmJob $record): bool => $record->status === 'Failed')
                        ->requiresConfirmation()
                        ->action(function (BmJob $record) {
                            $record->update([
                                'status' => 'Pending',
                                'error_message' => null,
                            ]);
                            // Try to dispatch if no other job is processing for this BM Account
                            if (!$record->dispatchIfAvailable()) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Job Queued')
                                    ->body('Another job is currently processing for this BM Account. This job will start when it finishes.')
                                    ->send();
                            }
                        }),

                    DeleteAction::make()
                        ->before(function (BmJob $record) {
                            $bmAccountId = $record->bm_account_id;
                            $wasProcessing = $record->status === 'Processing';

                            // Pause the job first
                            $record->update(['status' => 'Paused']);

                            // If it was processing, dispatch next job for this BM Account
                            if ($wasProcessing) {
                                BmJob::dispatchNextPendingJob($bmAccountId);
                            }
                        }),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll(config('adaccount.polling_interval', 5) . 's');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBmJobs::route('/'),
            'view' => Pages\ViewBmJob::route('/{record}'),
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
