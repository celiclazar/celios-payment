<?php

namespace Modules\Payment;

use Celios\Core\Models\User;
use Celios\Core\Services\ModuleRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Payment\Console\Commands\TestPaymentCommand;
use Modules\Payment\Enums\PaymentStatus;
use Modules\Payment\Filament\Resources\PaymentGatewayResource;
use Modules\Payment\Filament\Resources\PaymentResource;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentGatewayConfig;
use Modules\Payment\Policies\PaymentGatewayConfigPolicy;
use Modules\Payment\Policies\PaymentPolicy;
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
                TestPaymentCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if (file_exists(__DIR__.'/Routes/web.php')) {
            Route::middleware('web')
                ->group(__DIR__.'/Routes/web.php');
        }

        if (file_exists(__DIR__.'/Routes/api.php')) {
            Route::prefix('api')
                ->middleware('api')
                ->group(__DIR__.'/Routes/api.php');
        }

        if (file_exists(__DIR__.'/Resources/lang')) {
            $this->loadTranslationsFrom(__DIR__.'/Resources/lang', 'payment');
        }

        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PaymentGatewayConfig::class, PaymentGatewayConfigPolicy::class);

        if (class_exists(User::class)) {
            User::resolveRelationUsing('payments', fn (User $user) => $user->morphMany(Payment::class, 'payable')->orderByDesc('id'));
            User::resolveRelationUsing('latestPayment', fn (User $user) => $user->morphOne(Payment::class, 'payable')->latestOfMany());
            User::resolveRelationUsing('successfulPayments', fn (User $user) => $user->morphMany(Payment::class, 'payable')->where('status', PaymentStatus::PAID)->orderByDesc('id'));
            User::registerDynamicMethod('isPaid', fn (User $user): bool => $user->morphMany(Payment::class, 'payable')->where('status', PaymentStatus::PAID)->exists());
            User::registerDynamicMethod('totalPaidAmount', fn (User $user): float => (float) $user->morphMany(Payment::class, 'payable')->where('status', PaymentStatus::PAID)->sum('amount'));
        }

        if (class_exists(ModuleRegistry::class)) {
            ModuleRegistry::registerNamespace('payment', 'Modules\\Payment\\');
            ModuleRegistry::registerModule('payment', [
                'name' => 'Payment Processing',
                'description' => 'Process online payments, manage gateways (Stripe, Bank Transfer), and view audit logs.',
                'icon' => 'heroicon-o-credit-card',
                'resources' => [
                    PaymentResource::class,
                    PaymentGatewayResource::class,
                ],
            ]);
        }
    }
}
