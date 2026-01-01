<?php

namespace App\Providers;

use App\Models\BmJob;
use App\Observers\BmJobObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        BmJob::observe(BmJobObserver::class);
    }
}
