<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (!$this->app->isLocal()) {
            $origins = config('cors.allowed_origins');
            if (empty($origins)) {
                throw new \RuntimeException(
                    'CORS_ALLOWED_ORIGINS must be set in non-local environments. '
                    . 'If this appears after php artisan config:cache, rebuild the cache after updating .env.'
                );
            }
        }
    }
}
