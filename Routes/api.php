<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\Api\V1\PaymentController;

Route::prefix('v1')->group(function () {
    Route::middleware('module:payment')->group(function () {
        Route::get('/payment-gateways', [PaymentController::class, 'gateways']);
        Route::post('/payments/checkout', [PaymentController::class, 'checkout'])
            ->middleware('throttle:payments-checkout');
    });

    Route::middleware(['auth:sanctum', 'admin.access', 'module:payment'])
        ->prefix('admin')
        ->group(function () {
            Route::get('/payments', [PaymentController::class, 'index']);
            Route::get('/payments/{payment}', [PaymentController::class, 'show']);
            Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);
        });
});
