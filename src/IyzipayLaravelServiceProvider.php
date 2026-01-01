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
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('iyzipay.php')
        ]);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Scheduler Logic
        // We check if running in console first to prevent instantiating Schedule in HTTP requests
        if ($this->app->runningInConsole()) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('iyzipay:subscription_charge')->daily();
            });

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
