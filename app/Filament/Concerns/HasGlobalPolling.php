<?php

namespace App\Filament\Concerns;

trait HasGlobalPolling
{
    protected static function isGlobalPollingEnabled(): bool
    {
        return session('global_polling_enabled', false);
    }

    protected static function getPollingInterval(): ?string
    {
        if (!static::isGlobalPollingEnabled()) {
            return null;
        }

        return config('adaccount.polling_interval', 5) . 's';
    }
}
