<?php

namespace App\Filament\Resources\BmJobResource\Pages;

use App\Filament\Resources\BmJobResource;
use App\Models\BmJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ListBmJobs extends ListRecords
{
    protected static string $resource = BmJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pause_all')
                ->label('Pause All Jobs')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Pause All Jobs')
                ->modalDescription('This will pause all Processing and Pending jobs. Are you sure?')
                ->action(function () {
                    $lockKey = 'pause_all_jobs_lock';

                    // Prevent duplicate executions
                    if (Cache::has($lockKey)) {
                        Notification::make()
                            ->warning()
                            ->title('Operation in Progress')
                            ->body('Pause All is already being executed. Please wait.')
                            ->send();
                        return;
                    }

                    Cache::put($lockKey, true, 10); // 10 second lock

                    try {
                        $count = BmJob::whereIn('status', ['Processing', 'Pending'])
                            ->update(['status' => 'Paused']);

                        Notification::make()
                            ->success()
                            ->title('Jobs Paused')
                            ->body("{$count} job(s) have been paused.")
                            ->send();
                    } finally {
                        Cache::forget($lockKey);
                    }
                }),

            Actions\Action::make('resume_all')
                ->label('Resume All Jobs')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Resume All Paused Jobs')
                ->modalDescription('This will resume all Paused jobs and queue them for processing. Are you sure?')
                ->action(function () {
                    $lockKey = 'resume_all_jobs_lock';

                    // Prevent duplicate executions
                    if (Cache::has($lockKey)) {
                        Notification::make()
                            ->warning()
                            ->title('Operation in Progress')
                            ->body('Resume All is already being executed. Please wait.')
                            ->send();
                        return;
                    }

                    Cache::put($lockKey, true, 10); // 10 second lock

                    try {
                        // Get all paused jobs grouped by BM Account
                        $pausedJobs = BmJob::where('status', 'Paused')
                            ->orderBy('created_at', 'asc')
                            ->get()
                            ->groupBy('bm_account_id');

                        $count = 0;
                        $dispatchedCount = 0;

                        // Update all to Pending first
                        foreach ($pausedJobs as $bmAccountId => $jobs) {
                            foreach ($jobs as $job) {
                                $job->update(['status' => 'Pending']);
                                $count++;
                            }
                        }

                        // Dispatch one job per BM Account if not already processing
                        foreach ($pausedJobs as $bmAccountId => $jobs) {
                            if (BmJob::dispatchNextPendingJob($bmAccountId)) {
                                $dispatchedCount++;
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title('Jobs Resumed')
                            ->body("{$count} job(s) set to Pending. {$dispatchedCount} job(s) dispatched immediately.")
                            ->send();
                    } finally {
                        Cache::forget($lockKey);
                    }
                }),

            Actions\CreateAction::make()
                ->modal()
                ->modalWidth('3xl')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = Auth::id();
                    return $data;
                })
                ->after(function ($record): void {
                    BmJob::dispatchNextPendingJob($record->bm_account_id);
                }),
        ];
    }
}
