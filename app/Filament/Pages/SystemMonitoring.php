<?php

namespace App\Filament\Pages;

use Dotswan\FilamentLaravelPulse\Widgets\PulseCache;
use Dotswan\FilamentLaravelPulse\Widgets\PulseExceptions;
use Dotswan\FilamentLaravelPulse\Widgets\PulseQueues;
use Dotswan\FilamentLaravelPulse\Widgets\PulseServers;
use Dotswan\FilamentLaravelPulse\Widgets\PulseSlowOutGoingRequests;
use Dotswan\FilamentLaravelPulse\Widgets\PulseSlowQueries;
use Dotswan\FilamentLaravelPulse\Widgets\PulseSlowRequests;
use Dotswan\FilamentLaravelPulse\Widgets\PulseUsage;
use Dotswan\FilamentLaravelPulse\Widgets\PulseSlowJobs;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Pages\Page;

class SystemMonitoring extends Page
{
    use HasFiltersAction;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected string $view = 'filament.pages.system-monitoring';

    protected static ?string $navigationLabel = 'System Monitoring';

    protected static ?string $title = 'System Monitoring';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    public function getColumns(): int|string|array
    {
        return 12;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('1h')
                    ->label('1 Hour')
                    ->action(fn () => $this->redirect(static::getUrl())),
                Action::make('24h')
                    ->label('24 Hours')
                    ->action(fn () => $this->redirect(static::getUrl(['period' => '24_hours']))),
                Action::make('7d')
                    ->label('7 Days')
                    ->action(fn () => $this->redirect(static::getUrl(['period' => '7_days']))),
            ])
                ->label(__('Filter'))
                ->icon('heroicon-m-funnel')
                ->color('gray')
                ->button(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PulseServers::class,
            PulseCache::class,
            PulseExceptions::class,
            PulseUsage::class,
            PulseQueues::class,
            PulseSlowQueries::class,
            PulseSlowRequests::class,
            PulseSlowOutGoingRequests::class,
            PulseSlowJobs::class,
        ];
    }
}
