<?php

namespace App\Observers;

use App\Models\BmAccount;
use Illuminate\Support\Facades\Log;

class BmAccountObserver
{
    /**
     * Handle the BmAccount "deleted" event.
     * Cascade soft delete to all related BmJobs (which will cascade to AdAccounts)
     */
    public function deleted(BmAccount $bmAccount): void
    {
        if ($bmAccount->isForceDeleting()) {
            // Force deleting - remove all relationships
            return;
        }

        // Soft delete all related BmJobs (this will trigger BmJobObserver)
        $bmAccount->bmJobs()->chunkById(500, function ($jobs) {
            foreach ($jobs as $job) {
                if (!$job->trashed()) {
                    $job->delete();
                }
            }
        });

        // Also soft delete ad accounts directly related to this BM account
        $bmAccount->adAccounts()->chunkById(500, function ($adAccounts) {
            foreach ($adAccounts as $adAccount) {
                if (!$adAccount->trashed()) {
                    $adAccount->delete();
                }
            }
        });
    }

    /**
     * Handle the BmAccount "restored" event.
     * Restore all soft-deleted children
     */
    public function restored(BmAccount $bmAccount): void
    {
        // Restore all soft-deleted BmJobs
        $bmAccount->bmJobs()->onlyTrashed()->chunkById(500, function ($jobs) {
            foreach ($jobs as $job) {
                $job->restore();
            }
        });

        // Restore all soft-deleted AdAccounts
        $bmAccount->adAccounts()->onlyTrashed()->chunkById(500, function ($adAccounts) {
            foreach ($adAccounts as $adAccount) {
                $adAccount->restore();
            }
        });
    }
}
