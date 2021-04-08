<?php

namespace ALajusticia\SanctumTracker;

use Illuminate\Support\ServiceProvider;

class SanctumTrackerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Merge default config
        $this->mergeConfigFrom(
            __DIR__.'/../config/sanctum_tracker.php', 'sanctum_tracker'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/sanctum_tracker.php' => config_path('sanctum_tracker.php'),
        ], 'config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
