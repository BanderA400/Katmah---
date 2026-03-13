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
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('app')
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification(isRequired: false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.auth.back-to-home')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_REGISTER_FORM_AFTER,
                fn (): string => view('filament.auth.back-to-home')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_AFTER,
                fn (): string => view('filament.auth.back-to-home')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_END,
                fn (): string => request()->routeIs('filament.admin.auth.email-verification.prompt')
                    ? view('filament.auth.back-to-home')->render()
                    : '',
            )

            // === الهوية البصرية ===
            ->brandName('ختمة')
            ->brandLogo(asset('images/brand/khatma-logo-light.svg'))
            ->darkModeBrandLogo(asset('images/brand/khatma-logo-dark.svg'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/brand/khatma-mark.svg'))

            // === الألوان ===
            ->colors([
                'primary' => [
                    50 => '#f3e8ff',
                    100 => '#e4ccff',
                    200 => '#c9a0f5',
                    300 => '#a86de8',
                    400 => '#8b47d4',
                    500 => '#6D28D9',
                    600 => '#5B21B6',
                    700 => '#4C1D95',
                    800 => '#3B1578',
                    900 => '#2E1065',
                    950 => '#1a0a3e',
                ],
                'gray' => [
                    50 => '#faf9fc',
                    100 => '#f3f1f6',
                    200 => '#e8e5ed',
                    300 => '#d4d0dc',
                    400 => '#a8a2b4',
                    500 => '#7c7590',
                    600 => '#5e586e',
                    700 => '#443f52',
                    800 => '#2d2a38',
                    900 => '#1a1822',
                    950 => '#0f0e15',
                ],
                'success' => [
                    50 => '#ecfdf5',
                    100 => '#d1fae5',
                    200 => '#a7f3d0',
                    300 => '#6ee7b7',
                    400 => '#34d399',
                    500 => '#10B981',
                    600 => '#059669',
                    700 => '#047857',
                    800 => '#065f46',
                    900 => '#064e3b',
                    950 => '#022c22',
                ],
                'warning' => [
                    50 => '#fffbeb',
                    100 => '#fef3c7',
                    200 => '#fde68a',
                    300 => '#fcd34d',
                    400 => '#F59E0B',
                    500 => '#D97706',
                    600 => '#B45309',
                    700 => '#92400e',
                    800 => '#78350f',
                    900 => '#451a03',
                    950 => '#27130a',
                ],
                'danger' => [
                    50 => '#fef2f2',
                    100 => '#fee2e2',
                    200 => '#fecaca',
                    300 => '#fca5a5',
                    400 => '#f87171',
                    500 => '#EF4444',
                    600 => '#dc2626',
                    700 => '#b91c1c',
                    800 => '#991b1b',
                    900 => '#7f1d1d',
                    950 => '#450a0a',
                ],
            ])

            // === الخط ===
            ->font('Tajawal')

            // === الصفحات والويدجتات ===
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])

            // === Middleware ===
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
