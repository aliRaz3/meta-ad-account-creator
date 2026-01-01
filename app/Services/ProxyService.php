<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProxy;
use Illuminate\Support\Facades\Log;

class ProxyService
{
    protected static int $roundRobinIndex = 0;

    /**
     * Get the next proxy for the user based on their rotation type
     */
    public function getNextProxy(User $user): ?UserProxy
    {
        $settings = $user->getOrCreateSettings();

        if (!$settings->proxy_enabled) {
            return null;
        }

        $proxies = $user->activeProxies()->get();

        if ($proxies->isEmpty()) {
            return null;
        }

        return match ($settings->proxy_rotation_type) {
            'random' => $this->getRandomProxy($proxies),
            'sequential' => $this->getSequentialProxy($proxies),
            default => $this->getRoundRobinProxy($proxies),
        };
    }

    /**
     * Get a random proxy from the collection
     */
    protected function getRandomProxy($proxies): ?UserProxy
    {
        return $proxies->random();
    }

    /**
     * Get the next proxy in round-robin fashion
     */
    protected function getRoundRobinProxy($proxies): ?UserProxy
    {
        if ($proxies->isEmpty()) {
            return null;
        }

        $proxy = $proxies->get(self::$roundRobinIndex % $proxies->count());
        self::$roundRobinIndex++;

        return $proxy;
    }

    /**
     * Get the least recently used proxy (sequential)
     */
    protected function getSequentialProxy($proxies): ?UserProxy
    {
        return $proxies->sortBy('last_used_at')->first();
    }

    /**
     * Parse bulk proxy URLs
     * Supported formats:
     * - http://host:port
     * - http://username:password@host:port
     * - socks5://host:port
     */
    public function parseBulkProxies(string $bulkText): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $bulkText)));
        $proxies = [];

        foreach ($lines as $line) {
            $parsed = $this->parseProxyUrl($line);
            if ($parsed) {
                $proxies[] = $parsed;
            }
        }

        return $proxies;
    }

    /**
     * Parse a single proxy URL
     */
    protected function parseProxyUrl(string $url): ?array
    {
        // Parse URL
        $parts = parse_url($url);

        if (!$parts || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return [
            'protocol' => $parts['scheme'],
            'host' => $parts['host'],
            'port' => $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 8080),
            'username' => $parts['user'] ?? null,
            'password' => $parts['pass'] ?? null,
            'is_active' => true,
            'is_validated' => false,
        ];
    }

    /**
     * Validate all proxies for a user
     */
    public function validateAllProxies(User $user): array
    {
        $proxies = $user->proxies()->where('is_active', true)->get();
        $results = [
            'total' => $proxies->count(),
            'validated' => 0,
            'failed' => 0,
        ];

        foreach ($proxies as $proxy) {
            if ($proxy->validate()) {
                $results['validated']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Create proxies from bulk text for a user
     */
    public function createBulkProxies(User $user, string $bulkText): int
    {
        $parsedProxies = $this->parseBulkProxies($bulkText);
        $created = 0;

        foreach ($parsedProxies as $proxyData) {
            $proxyData['user_id'] = $user->id;

            // Check if proxy already exists
            $exists = UserProxy::where('user_id', $user->id)
                ->where('host', $proxyData['host'])
                ->where('port', $proxyData['port'])
                ->exists();

            if (!$exists) {
                UserProxy::create($proxyData);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Use a proxy and handle success/failure
     */
    public function useProxy(?UserProxy $proxy, bool $success, ?string $error = null): void
    {
        if (!$proxy) {
            return;
        }

        $proxy->markUsed();

        if ($success) {
            $proxy->recordSuccess();
        } else {
            $proxy->recordFailure($error ?? 'Unknown error');
            Log::warning('Proxy failed', [
                'proxy_id' => $proxy->id,
                'error' => $error,
            ]);
        }
    }
}
