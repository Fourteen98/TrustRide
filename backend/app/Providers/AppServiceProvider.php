<?php

namespace App\Providers;

use App\Support\Contracts\SmsSender;
use App\Support\LogSmsSender;
use App\Support\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Must exist before any module registers itself.
        $this->app->singleton(ModuleRegistry::class);

        // A fallback. Messaging rebinds this to its own SMS channel when it registers.
        $this->app->bind(SmsSender::class, LogSmsSender::class);
    }

    public function boot(): void
    {
        //
    }
}
