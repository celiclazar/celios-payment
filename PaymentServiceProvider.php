<?php

namespace Modules\Payment;

use Illuminate\Support\ServiceProvider;
use Modules\Payment\Services\PaymentManager;
use Modules\Payment\Services\PaymentService;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/payment.php', 'payment');

        $this->app->singleton(PaymentManager::class, function ($app) {
            return new PaymentManager($app);
        });

        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService($app->make(PaymentManager::class));
        });

        $this->app->alias(PaymentService::class, 'payment');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/Config/payment.php' => config_path('payment.php'),
            ], 'payment-config');

            $this->commands([
                \Modules\Payment\Console\Commands\TestPaymentCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if (file_exists(__DIR__.'/Routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/Routes/web.php');
        }

        if (file_exists(__DIR__.'/Resources/lang')) {
            $this->loadTranslationsFrom(__DIR__.'/Resources/lang', 'payment');
        }
    }
}
