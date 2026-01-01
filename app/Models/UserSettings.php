<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    protected $fillable = [
        'user_id',
        'proxy_enabled',
        'proxy_rotation_type',
        'telegram_notifications_enabled',
        'additional_settings',
    ];

    protected $casts = [
        'proxy_enabled' => 'boolean',
        'telegram_notifications_enabled' => 'boolean',
        'additional_settings' => 'array',
    ];

    protected $attributes = [
        'proxy_enabled' => false,
        'proxy_rotation_type' => 'round-robin',
        'telegram_notifications_enabled' => true,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get available proxy rotation types
     */
    public static function getRotationTypes(): array
    {
        return [
            'round-robin' => 'Round Robin (Sequential rotation)',
            'random' => 'Random (Pick randomly each time)',
            'sequential' => 'Sequential (Use until fails, then next)',
        ];
    }
}
