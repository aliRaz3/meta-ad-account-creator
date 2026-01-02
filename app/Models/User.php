<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;

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
        'is_admin',
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
            'is_admin' => 'boolean',
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

    /**
     * Check if the user can access the Filament panel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true; // All authenticated users can access panel
    }

    /**
     * Check if the user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Determine if the user can impersonate other users
     */
    public function canImpersonate(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Determine if the user can be impersonated
     */
    public function canBeImpersonated(): bool
    {
        // Prevent impersonating other admins
        return !$this->isAdmin();
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

    public function bmAccounts()
    {
        return $this->hasMany(BmAccount::class);
    }

    public function bmJobs()
    {
        return $this->hasMany(BmJob::class);
    }

    public function adAccounts()
    {
        return $this->hasMany(AdAccount::class);
    }

    public function canAccessDebuggers(): bool
    {
        // can access pulse, horizon, telescope, filament debugger
        return $this->id === 1;
    }
}
