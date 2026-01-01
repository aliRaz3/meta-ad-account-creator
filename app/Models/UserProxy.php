<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProxy extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'protocol',
        'host',
        'port',
        'username',
        'password',
        'is_active',
        'is_validated',
        'last_validated_at',
        'last_used_at',
        'success_count',
        'failure_count',
        'last_error',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_validated' => 'boolean',
        'last_validated_at' => 'datetime',
        'last_used_at' => 'datetime',
        'port' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the proxy URL
     */
    public function getProxyUrl(): string
    {
        $auth = '';
        if ($this->username && $this->password) {
            $auth = "{$this->username}:{$this->password}@";
        }

        return "{$this->protocol}://{$auth}{$this->host}:{$this->port}";
    }

    /**
     * Mark proxy as used
     */
    public function markUsed(): void
    {
        $this->update([
            'last_used_at' => now(),
        ]);
    }

    /**
     * Record successful request
     */
    public function recordSuccess(): void
    {
        $this->increment('success_count');
        $this->update(['last_error' => null]);
    }

    /**
     * Record failed request
     */
    public function recordFailure(string $error): void
    {
        $this->increment('failure_count');
        $this->update(['last_error' => $error]);

        // Auto-disable proxy if failure rate is too high
        if ($this->failure_count >= 10 && $this->success_count < 5) {
            $this->update(['is_active' => false]);
        }
    }

    /**
     * Validate proxy connectivity
     */
    public function validate(): bool
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withOptions(['proxy' => $this->getProxyUrl(), 'verify' => false])
                ->get('https://api.ipify.org?format=json');

            if ($response->successful()) {
                $this->update([
                    'is_validated' => true,
                    'last_validated_at' => now(),
                    'last_error' => null,
                ]);
                return true;
            }

            $this->update([
                'is_validated' => false,
                'last_error' => 'Validation failed: ' . $response->status(),
            ]);
            return false;
        } catch (\Exception $e) {
            $this->update([
                'is_validated' => false,
                'last_error' => 'Validation error: ' . $e->getMessage(),
            ]);
            return false;
        }
    }
}
