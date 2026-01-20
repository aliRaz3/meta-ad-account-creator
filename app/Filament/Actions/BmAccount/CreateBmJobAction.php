<?php

namespace App\Filament\Actions\BmAccount;

use App\Models\BmAccount;
use App\Models\BmJob;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Illuminate\Support\Facades\Auth;

class CreateBmJobAction
{
    public static function make(): Action
    {
        return Action::make('create_job')
            ->label('Create BM Job')
            ->icon('heroicon-o-plus-circle')
            ->color('success')
            ->modal()
            ->modalWidth('3xl')
            ->modalSubmitAction(fn ($action) => $action->color('primary'))
            ->schema(static::schema())
            ->action(fn (BmAccount $record, array $data) => static::handle($record, $data));
    }

    protected static function schema(): array
    {
        return [
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
        ];
    }

    protected static function handle(BmAccount $record, array $data): void
    {
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
    }
}
