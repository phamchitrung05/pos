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
                    50 => 'oklch(0.97 0.015 255)',
                    100 => 'oklch(0.94 0.03 255)',
                    200 => 'oklch(0.88 0.06 255)',
                    300 => 'oklch(0.80 0.10 255)',
                    400 => 'oklch(0.68 0.17 255)',
                    500 => 'oklch(0.55 0.22 255)',
                    600 => 'oklch(0.50 0.22 255)',
                    700 => 'oklch(0.43 0.19 255)',
                    800 => 'oklch(0.36 0.15 255)',
                    900 => 'oklch(0.28 0.11 255)',
                    950 => 'oklch(0.20 0.08 255)',
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
