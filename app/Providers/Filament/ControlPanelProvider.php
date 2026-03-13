<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetFilamentArabicLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ControlPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('control')
            ->path('control')
            ->login()
            ->passwordReset()
            ->emailVerification(isRequired: true)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(
                PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_AFTER,
                fn (): string => view('filament.auth.back-to-home')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_END,
                fn (): string => request()->routeIs('filament.control.auth.email-verification.prompt')
                    ? view('filament.auth.back-to-home')->render()
                    : '',
            )
            ->brandName('ختمة | مركز التحكم')
            ->brandLogo(asset('images/brand/khatma-logo-light.svg'))
            ->darkModeBrandLogo(asset('images/brand/khatma-logo-dark.svg'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/brand/khatma-mark.svg'))
            ->font('Tajawal')
            ->discoverResources(in: app_path('Filament/Control/Resources'), for: 'App\\Filament\\Control\\Resources')
            ->discoverPages(in: app_path('Filament/Control/Pages'), for: 'App\\Filament\\Control\\Pages')
            ->discoverWidgets(in: app_path('Filament/Control/Widgets'), for: 'App\\Filament\\Control\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetFilamentArabicLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
