<?php

namespace App\Providers\Filament;

use App\Models\Store;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
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
            // Store là tenant của panel. User::canAccessTenant() là lớp bảo vệ
            // bắt buộc để chặn người dùng đoán ID chi nhánh trên URL.
            ->tenant(Store::class)
            ->tenantRoutePrefix('store')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            // Khi thiếu policy hoặc method policy, Filament phải báo lỗi thay
            // vì mặc định cấp quyền và có nguy cơ làm lộ dữ liệu tenant.
            ->strictAuthorization()
            ->colors([
                'primary' => [
                    50 => 'oklch(0.97 0.018 65.48)',
                    100 => 'oklch(0.94 0.035 65.48)',
                    200 => 'oklch(0.90 0.060 65.48)',
                    300 => 'oklch(0.85 0.095 65.48)',
                    400 => 'oklch(0.80 0.130 65.48)',
                    500 => 'oklch(0.7604 0.1552 65.48)',
                    600 => 'oklch(0.68 0.145 65.48)',
                    700 => 'oklch(0.59 0.125 65.48)',
                    800 => 'oklch(0.50 0.105 65.48)',
                    900 => 'oklch(0.42 0.085 65.48)',
                    950 => 'oklch(0.32 0.065 65.48)',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
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
            ]);
    }
}
