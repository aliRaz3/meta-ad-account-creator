<?php

namespace App\Observers;

use App\Jobs\SendTelegramNotification;
use App\Models\BmJob;
use Illuminate\Support\Facades\Log;

class BmJobObserver
{
    /**
     * Handle the BmJob "updating" event.
     */
    public function updating(BmJob $bmJob): void
    {
        if (!$bmJob->isDirty('status')) {
            return;
        }

        $oldStatus = $bmJob->getOriginal('status');
        $newStatus = $bmJob->status;

        // Track time when status changes
        $now = now();

        // Handle status transitions
        if ($oldStatus === 'Pending' && $newStatus === 'Processing') {
            // Pending -> Processing: Set started_at
            $bmJob->started_at = $now;
            $this->dispatchNotification($bmJob, 'job_started');
        } elseif ($oldStatus === 'Processing' && $newStatus === 'Paused') {
            // Processing -> Paused: Calculate running time and set paused_at
            $bmJob->paused_at = $now;
            if ($bmJob->started_at) {
                $runningSeconds = $now->diffInSeconds($bmJob->resumed_at ?? $bmJob->started_at);
                $bmJob->total_running_seconds += $runningSeconds;
            }
            $this->dispatchNotification($bmJob, 'job_paused');
        } elseif ($oldStatus === 'Paused' && $newStatus === 'Processing') {
            // Paused -> Processing: Set resumed_at
            $bmJob->resumed_at = $now;
            $this->dispatchNotification($bmJob, 'job_resumed');
        } elseif ($oldStatus === 'Processing' && $newStatus === 'Completed') {
            // Processing -> Completed: Calculate final running time
            $bmJob->completed_at = $now;
            if ($bmJob->started_at) {
                $runningSeconds = $now->diffInSeconds($bmJob->resumed_at ?? $bmJob->started_at);
                $bmJob->total_running_seconds += $runningSeconds;

                // Calculate accounts per minute
                if ($bmJob->total_running_seconds > 0) {
                    $minutes = $bmJob->total_running_seconds / 60;
                    $bmJob->accounts_per_minute = round($bmJob->processed_ad_accounts / $minutes, 2);
                }
            }
            $this->dispatchNotification($bmJob, 'job_completed');
        } elseif ($newStatus === 'Failed') {
            // Any -> Failed: Calculate running time if was processing
            if ($oldStatus === 'Processing' && $bmJob->started_at) {
                $runningSeconds = $now->diffInSeconds($bmJob->resumed_at ?? $bmJob->started_at);
                $bmJob->total_running_seconds += $runningSeconds;
            }
            $this->dispatchNotification($bmJob, 'job_failed');
        }
    }

    /**
     * Handle the BmJob "updated" event.
     */
    public function updated(BmJob $bmJob): void
    {
        // Check for progress milestones
        if ($bmJob->isDirty('processed_ad_accounts') && $bmJob->status === 'Processing') {
            $progress = ($bmJob->processed_ad_accounts / $bmJob->total_ad_accounts) * 100;

            // Dispatch notifications at 25%, 50%, 75% milestones
            if ($progress >= 25 && $progress < 30 && !$bmJob->wasRecentlyCreated) {
                $this->dispatchNotification($bmJob, 'progress_25');
            } elseif ($progress >= 50 && $progress < 55) {
                $this->dispatchNotification($bmJob, 'progress_50');
            } elseif ($progress >= 75 && $progress < 80) {
                $this->dispatchNotification($bmJob, 'progress_75');
            }
        }
    }

    /**
     * Dispatch Telegram notification for the job event.
     */
    protected function dispatchNotification(BmJob $bmJob, string $event): void
    {
        try {
            $user = $bmJob->user;

            if (!$user) {
                Log::warning('Cannot dispatch notification: BmJob has no associated user', [
                    'bm_job_id' => $bmJob->id,
                    'event' => $event,
                ]);
                return;
            }

            // Check if user has notifications enabled
            $settings = $user->getOrCreateSettings();
            if (!$settings->telegram_notifications_enabled) {
                return;
            }

            // Prepare notification data
            $data = [
                'job_id' => $bmJob->bmAccount->id . '-' . $bmJob->id,
                'bm_account_name' => $bmJob->bmAccount?->title ?? 'Unknown',
                'pattern' => $bmJob->pattern,
                'total_accounts' => $bmJob->total_ad_accounts,
                'processed_accounts' => $bmJob->processed_ad_accounts,
                'progress' => $bmJob->total_ad_accounts > 0
                    ? round(($bmJob->processed_ad_accounts / $bmJob->total_ad_accounts) * 100, 1)
                    : 0,
                'status' => $bmJob->status,
                'duration' => $bmJob->total_running_seconds ? gmdate("H:i:s", $bmJob->total_running_seconds) : '00:00:00',
                'accounts_per_minute' => $bmJob->accounts_per_minute,
                'started_at' => $bmJob->started_at?->format('Y-m-d H:i:s'),
                'completed_at' => $bmJob->completed_at?->format('Y-m-d H:i:s'),
                'total_running_seconds' => $bmJob->total_running_seconds,

            ];

            SendTelegramNotification::dispatch($user, $event, $data);

        } catch (\Exception $e) {
            Log::error('Failed to dispatch Telegram notification', [
                'bm_job_id' => $bmJob->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the BmJob "deleted" event.
     * Cascade soft delete to all related AdAccounts
     */
    public function deleted(BmJob $bmJob): void
    {
        if ($bmJob->isForceDeleting()) {
            // Force deleting - remove all relationships
            return;
        }

        // Soft delete all related AdAccounts
        $bmJob->adAccounts()->each(function ($adAccount) {
            if (!$adAccount->trashed()) {
                $adAccount->delete();
            }
        });
    }

    /**
     * Handle the BmJob "restored" event.
     * Restore all soft-deleted children
     */
    public function restored(BmJob $bmJob): void
    {
        // Restore all soft-deleted AdAccounts
        $bmJob->adAccounts()->onlyTrashed()->each(function ($adAccount) {
            $adAccount->restore();
        });
    }
}
