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
        $bmAccount->bmJobs()->each(function ($job) {
            if (!$job->trashed()) {
                $job->delete();
            }
        });

        // Also soft delete ad accounts directly related to this BM account
        $bmAccount->adAccounts()->each(function ($adAccount) {
            if (!$adAccount->trashed()) {
                $adAccount->delete();
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
        $bmAccount->bmJobs()->onlyTrashed()->each(function ($job) {
            $job->restore();
        });

        // Restore all soft-deleted AdAccounts
        $bmAccount->adAccounts()->onlyTrashed()->each(function ($adAccount) {
            $adAccount->restore();
        });
    }
}
