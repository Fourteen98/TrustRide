<?php

namespace App\Admin;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The admin side. Staff log in here on the platform guard.
 *
 * It holds no business rules. Each module keeps its own screens in
 * modules/<Module>/Filament/Resources (and Filament/Pages) and the panel picks them up.
 * A screen only shows in the menu if the user has its permission.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('TrustRide')
            ->authGuard('platform')
            ->login()
            ->colors(['primary' => Color::Amber])
            // The order of the menu: what operators use most comes first.
            ->navigationGroups(['Subscriptions', 'Riders', 'Drivers', 'Network', 'Access'])
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);

        foreach (glob(base_path('modules/*/Filament/Resources'), GLOB_ONLYDIR) as $directory) {
            $module = basename(dirname($directory, 2));
            $panel->discoverResources(in: $directory, for: "Modules\\{$module}\\Filament\\Resources");
        }

        foreach (glob(base_path('modules/*/Filament/Pages'), GLOB_ONLYDIR) as $directory) {
            $module = basename(dirname($directory, 2));
            $panel->discoverPages(in: $directory, for: "Modules\\{$module}\\Filament\\Pages");
        }

        return $panel;
    }
}
