<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTelegramBot extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'bot_token',
        'chat_id',
        'notification_preferences',
        'is_active',
        'last_notification_at',
    ];

    protected $casts = [
        'notification_preferences' => 'array',
        'is_active' => 'boolean',
        'last_notification_at' => 'datetime',
    ];

    protected $hidden = [
        'bot_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this bot should receive notifications for a specific event
     */
    public function shouldNotify(string $event): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $preferences = $this->notification_preferences ?? [];
        return in_array($event, $preferences);
    }

    /**
     * Available notification events
     */
    public static function getNotificationEvents(): array
    {
        return [
            'job_started' => 'BM Job Started',
            'job_completed' => 'BM Job Completed',
            'job_failed' => 'BM Job Failed',
            'job_paused' => 'BM Job Paused',
            'job_resumed' => 'BM Job Resumed',
            'progress_25' => 'Progress: 25% Complete',
            'progress_50' => 'Progress: 50% Complete',
            'progress_75' => 'Progress: 75% Complete',
            'system_errors' => 'System Errors & Warnings',
        ];
    }

    /**
     * Mark this bot as having sent a notification
     */
    public function markNotificationSent(): void
    {
        $this->update(['last_notification_at' => now()]);
    }
}
