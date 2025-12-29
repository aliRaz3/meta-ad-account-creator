<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BmJob extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'bm_account_id',
        'status',
        'total_ad_accounts',
        'starting_ad_account_no',
        'pattern',
        'currency',
        'time_zone',
        'processed_ad_accounts',
        'error_message',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function bmAccount()
    {
        return $this->belongsTo(BmAccount::class);
    }

    public function adAccounts()
    {
        return $this->hasMany(AdAccount::class);
    }

    /**
     * Check if there's already a processing job for this BM Account
     */
    public static function hasProcessingJob(int $bmAccountId): bool
    {
        return static::where('bm_account_id', $bmAccountId)
            ->where('status', 'Processing')
            ->exists();
    }

    /**
     * Dispatch the next pending job for a BM Account if no job is currently processing
     */
    public static function dispatchNextPendingJob(int $bmAccountId): ?self
    {
        // Check if there's already a processing job
        if (static::hasProcessingJob($bmAccountId)) {
            return null;
        }

        // Get the oldest pending job for this BM Account
        $nextJob = static::where('bm_account_id', $bmAccountId)
            ->where('status', 'Pending')
            ->orderBy('created_at', 'asc')
            ->first();

        if ($nextJob) {
            \App\Jobs\ProcessBmJob::dispatch($nextJob);
            return $nextJob;
        }

        return null;
    }

    /**
     * Dispatch this job only if no other job is processing for the same BM Account
     */
    public function dispatchIfAvailable(): bool
    {
        if (static::hasProcessingJob($this->bm_account_id)) {
            return false;
        }

        \App\Jobs\ProcessBmJob::dispatch($this);
        return true;
    }
}
