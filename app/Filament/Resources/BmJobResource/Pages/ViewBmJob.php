<?php

namespace App\Filament\Resources\BmJobResource\Pages;

use App\Filament\Resources\BmJobResource;
use App\Jobs\ProcessBmJob;
use App\Models\AdAccount;
use App\Models\BmJob;
use App\Services\MetaApiService;
use Filament\Actions;
use Filament\Actions\Action as TableAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ViewBmJob extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = BmJobResource::class;

    protected string $view = 'filament.resources.bm-job-resource.pages.view-bm-job';

    public function getTitle(): string
    {
        return 'BM Job #' . $this->record->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pause')
                ->label('Pause Job')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn(): bool => $this->record->status === 'Processing')
                ->requiresConfirmation()
                ->modalHeading('Pause Job')
                ->modalDescription('Are you sure you want to pause this job? It will stop after completing the current ad account.')
                ->action(function () {
                    $this->record->update(['status' => 'Paused']);
                    $this->refreshFormData([
                        'status',
                    ]);
                }),

            Actions\Action::make('resume')
                ->label('Resume Job')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn(): bool => $this->record->status === 'Paused')
                ->requiresConfirmation()
                ->modalHeading('Resume Job')
                ->modalDescription('Resume processing this job from where it left off?')
                ->action(function () {
                    $this->record->update(['status' => 'Pending']);
                    // Try to dispatch if no other job is processing for this BM Account
                    if (!$this->record->dispatchIfAvailable()) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Job Queued')
                            ->body('Another job is currently processing for this BM Account. This job will start when it finishes.')
                            ->send();
                    }
                    $this->refreshFormData([
                        'status',
                    ]);
                }),

            Actions\Action::make('retry_job')
                ->label('Retry Job')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn(): bool => $this->record->status === 'Failed')
                ->requiresConfirmation()
                ->modalHeading('Retry Job')
                ->modalDescription('Retry this failed job?')
                ->action(function () {
                    $this->record->update([
                        'status' => 'Pending',
                        'error_message' => null,
                    ]);
                    // Try to dispatch if no other job is processing for this BM Account
                    if (!$this->record->dispatchIfAvailable()) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Job Queued')
                            ->body('Another job is currently processing for this BM Account. This job will start when it finishes.')
                            ->send();
                    }
                    $this->refreshFormData([
                        'status',
                        'error_message',
                    ]);
                }),

            Actions\Action::make('back')
                ->label('Back to Jobs')
                ->url(BmJobResource::getUrl('index'))
                ->color('gray'),

            Actions\DeleteAction::make()
                ->before(function () {
                    $bmAccountId = $this->record->bm_account_id;
                    $wasProcessing = $this->record->status === 'Processing';

                    // Pause the job first
                    $this->record->update(['status' => 'Paused']);

                    // If it was processing, dispatch next job for this BM Account
                    if ($wasProcessing) {
                        BmJob::dispatchNextPendingJob($bmAccountId);
                    }
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job Information')
                    ->components([
                        TextEntry::make('bmAccount.title')
                            ->label('BM Account'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'Pending' => 'gray',
                                'Processing' => 'info',
                                'Paused' => 'warning',
                                'Completed' => 'success',
                                'Failed' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('pattern')
                            ->label('Pattern')
                            ->placeholder('Not set'),

                        TextEntry::make('starting_ad_account_no')
                            ->label('Starting Number'),

                        TextEntry::make('total_ad_accounts')
                            ->label('Total Ad Accounts'),

                        TextEntry::make('processed_ad_accounts')
                            ->label('Processed')
                            ->badge()
                            ->color('success'),

                        TextEntry::make('progress')
                            ->label('Progress')
                            ->state(function (): string {
                                $percentage = $this->record->total_ad_accounts > 0
                                    ? round(($this->record->processed_ad_accounts / $this->record->total_ad_accounts) * 100, 1)
                                    : 0;
                                return "{$percentage}%";
                            })
                            ->badge()
                            ->color(function (): string {
                                $percentage = $this->record->total_ad_accounts > 0
                                    ? ($this->record->processed_ad_accounts / $this->record->total_ad_accounts) * 100
                                    : 0;
                                if ($percentage >= 100) return 'success';
                                if ($percentage >= 50) return 'warning';
                                return 'gray';
                            })
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large),

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
                            ->label('Last Updated')
                            ->dateTime()
                            ->since(),
                    ])
                    ->columns(3),

                Section::make('Error Details')
                    ->components([
                        TextEntry::make('error_message')
                            ->label('Error Message')
                            ->color('danger')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(): bool => !empty($this->record->error_message)),
            ]);
    }

    /**
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                AdAccount::query()
                    ->where('bm_job_id', $this->record->id)
                    ->orderBy('created_at', 'asc')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Account Name')
                    ->searchable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('ad_account_id')
                    ->label('Meta Ad Account ID')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Copied!')
                    ->placeholder('Not created yet'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Pending' => 'gray',
                        'Created' => 'success',
                        'Failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('currency')
                    ->label('Currency'),

                TextColumn::make('created_at')
                    ->label('Created At')
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
            ])
            ->actions([
                TableAction::make('view_response')
                    ->label('View API Response')
                    ->icon('heroicon-o-code-bracket')
                    ->color('info')
                    ->modalContent(fn(AdAccount $record) => view('filament.modals.api-response', [
                        'response' => $record->api_response,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn(AdAccount $record): bool => !empty($record->api_response)),

                TableAction::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn(AdAccount $record): bool => $record->status === 'Failed')
                    ->requiresConfirmation()
                    ->modalHeading('Retry Creating Ad Account')
                    ->modalDescription(fn(AdAccount $record) => "Retry creating ad account: {$record->name}?")
                    ->action(function (AdAccount $record) {
                        $metaApiService = app(MetaApiService::class);
                        $bmAccount = $this->record->bmAccount;

                        try {
                            $result = $metaApiService->createAdAccount(
                                $bmAccount->business_portfolio_id,
                                $bmAccount->access_token,
                                $record->name,
                                $record->currency,
                                $record->time_zone
                            );

                            if ($result['success']) {
                                $record->update([
                                    'status' => 'Created',
                                    'ad_account_id' => $result['data']['id'] ?? null,
                                    'api_response' => json_encode($result['response']),
                                ]);

                                // Increment processed count
                                $this->record->increment('processed_ad_accounts');

                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Ad Account Created')
                                    ->body("Successfully created: {$record->name}")
                                    ->send();
                            } else {
                                $record->update([
                                    'api_response' => json_encode($result['response']),
                                ]);

                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Failed to Create')
                                    ->body($metaApiService->formatError($result))
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->poll(config('adaccount.polling_interval', 5) . 's')
            ->heading('Ad Accounts')
            ->description('List of all ad accounts being created by this job');
    }

    protected function getPollingInterval(): ?string
    {
        return config('adaccount.polling_interval', 5) . 's';
    }
}
