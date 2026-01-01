<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->id === 1;
    }

    /**
     * Get the user's Telegram bots
     */
    public function telegramBots()
    {
        return $this->hasMany(UserTelegramBot::class);
    }

    /**
     * Get the user's active Telegram bots
     */
    public function activeTelegramBots()
    {
        return $this->hasMany(UserTelegramBot::class)->where('is_active', true);
    }

    /**
     * Get the user's proxies
     */
    public function proxies()
    {
        return $this->hasMany(UserProxy::class);
    }

    /**
     * Get the user's active proxies
     */
    public function activeProxies()
    {
        return $this->hasMany(UserProxy::class)
            ->where('is_active', true)
            ->where('is_validated', true);
    }

    /**
     * Get the user's settings
     */
    public function settings()
    {
        return $this->hasOne(UserSettings::class);
    }

    /**
     * Get or create user settings
     */
    public function getOrCreateSettings(): UserSettings
    {
        return $this->settings()->firstOrCreate(
            ['user_id' => $this->id],
            [
                'proxy_enabled' => false,
                'proxy_rotation_type' => 'round-robin',
                'telegram_notifications_enabled' => true,
            ]
        );
    }
}
