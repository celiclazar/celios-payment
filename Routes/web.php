<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentRedirectController;
use Modules\Payment\Http\Controllers\PaymentWebhookController;

Route::prefix('payments')->name('payments.')->middleware('module:payment')->group(function () {
    // Universal Webhook Endpoint
    Route::post('webhook/{gateway}', [PaymentWebhookController::class, 'handle'])
        ->name('webhook');

    // Customer Redirect Return and Cancel Endpoints
    Route::get('{gateway}/return', [PaymentRedirectController::class, 'handleReturn'])
        ->name('return');
    Route::get('{gateway}/cancel', [PaymentRedirectController::class, 'handleCancel'])
        ->name('cancel');
});
