<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
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
        \Illuminate\Validation\Rules\Password::defaults(fn () =>
            \Illuminate\Validation\Rules\Password::min(8)->rules([new \App\Rules\PasswordComposition])
        );
        Vite::prefetch(concurrency: 3);
    }
}
