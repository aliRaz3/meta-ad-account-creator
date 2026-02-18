<?php

namespace App\Providers\Filament;

use App\Livewire\GlobalPollingToggle;
use Boquizo\FilamentLogViewer\FilamentLogViewerPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;
use Stephenjude\FilamentDebugger\DebuggerPlugin;
use Tapp\FilamentAuthenticationLog\FilamentAuthenticationLogPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        Livewire::component('global-polling-toggle', GlobalPollingToggle::class);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('/')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentLogViewerPlugin::make()->navigationGroup('Debuggers'),
                DebuggerPlugin::make()->authorize(condition: fn() => auth()->user()->canAccessDebuggers()),
                FilamentAuthenticationLogPlugin::make(),
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn() => view('components.global-polling-toggle-wrapper')
            )
            ->spa(hasPrefetching: true);
    }
}
