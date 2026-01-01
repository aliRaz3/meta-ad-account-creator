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
        'started_at',
        'completed_at',
        'paused_at',
        'resumed_at',
        'total_running_seconds',
        'accounts_per_minute',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
        'total_running_seconds' => 'integer',
        'accounts_per_minute' => 'decimal:2',
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
     * Get the target accounts count (using total_ad_accounts)
     */
    public function getTargetAccountsAttribute(): int
    {
        return $this->total_ad_accounts ?? 0;
    }

    /**
     * Get the accounts created count (using processed_ad_accounts)
     */
    public function getAccountsCreatedAttribute(): int
    {
        return $this->processed_ad_accounts ?? 0;
    }

    /**
     * Get formatted running time
     */
    public function getFormattedRunningTimeAttribute(): string
    {
        $seconds = $this->total_running_seconds;

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return "{$minutes}m {$remainingSeconds}s";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m {$remainingSeconds}s";
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
