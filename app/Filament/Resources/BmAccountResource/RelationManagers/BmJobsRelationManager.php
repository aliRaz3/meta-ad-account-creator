<?php

namespace App\Filament\Resources\BmAccountResource\RelationManagers;

use App\Filament\Resources\BmJobResource;
use App\Models\BmAccount;
use App\Models\BmJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BmJobsRelationManager extends RelationManager
{
    protected static string $relationship = 'bmJobs';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
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
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Create BM Job')
                    ->icon('heroicon-o-plus')
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
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['bm_account_id'] = $this->getOwnerRecord()->id;
                        $data['user_id'] = Auth::id();
                        $data['status'] = 'Pending';
                        $data['processed_ad_accounts'] = 0;
                        return $data;
                    })
                    ->after(function (): void {
                        BmJob::dispatchNextPendingJob($this->getOwnerRecord()->id);
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(BmJob $record): string => BmJobResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll(config('adaccount.polling_interval', 5) . 's');
    }
}
