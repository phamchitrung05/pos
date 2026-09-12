<?php

namespace App\Providers;

use App\Models\Printer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Bucket riêng theo Printer tránh nhiều máy cùng NAT làm nghẽn callback của nhau.
        RateLimiter::for('print-agent', function (Request $request): Limit {
            $printer = $request->route('printer');
            $printerId = $printer instanceof Printer ? $printer->getKey() : $printer;

            return Limit::perMinute(180)->by("printer:{$printerId}|{$request->ip()}");
        });

        RateLimiter::for('pos-login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('pos-api', function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(240)->by("user:{$userId}|device:{$request->header('X-Device-ID')}|{$request->ip()}");
        });
    }
}
