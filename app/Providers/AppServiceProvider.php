<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Rate limiting на POST /api/contact — защита от спама.
        // Бэкенд хранилища лимитера — файловый кеш (CACHE_STORE=file в .env).
        RateLimiter::for('contact', function (Request $request) {
            return Limit::perMinutes(
                (int) config('contact.rate_limit.decay_minutes'),
                (int) config('contact.rate_limit.max_attempts'),
            )->by($request->ip())->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Слишком много запросов. Попробуйте позже.',
                ], 429, $headers);
            });
        });
    }
}
