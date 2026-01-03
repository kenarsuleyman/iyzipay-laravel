<?php

namespace Iyzico\IyzipayLaravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Iyzico\IyzipayLaravel\Commands\SubscriptionChargeCommand;

class IyzipayLaravelServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        $migrationPath = __DIR__ . '/../database/migrations';

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('iyzipay.php')
        ]);

        $this->publishes([
            $migrationPath => database_path('migrations')
        ], 'iyzipay-migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if (config('iyzipay.load_migrations', true)) {
            $this->loadMigrationsFrom($migrationPath);
        }

        // Register the command so it can be called manually or scheduled by the user
        if ($this->app->runningInConsole()) {
            $this->commands([
                SubscriptionChargeCommand::class
            ]);
        }
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/config.php',
            'iyzipay'
        );

        // Changed 'bind' to 'singleton' for better performance
        $this->app->singleton('iyzipay-laravel', function () {
            return new IyzipayLaravel();
        });
    }
}
