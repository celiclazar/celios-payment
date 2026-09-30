<?php

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Payment\Contracts\RedirectableGatewayInterface;
use Modules\Payment\Enums\PaymentStatus;
use Modules\Payment\Facades\Payment;
use Modules\Payment\Models\Payment as PaymentModel;

class PaymentRedirectController extends Controller
{
    /**
     * Handle customer return from offsite payment gateway.
     */
    public function handleReturn(string $gateway, Request $request): RedirectResponse
    {
        $driver = Payment::gateway($gateway);

        if ($driver instanceof RedirectableGatewayInterface) {
            $response = $driver->handleReturn($request);

            if ($response->reference) {
                $payment = PaymentModel::where('reference', $response->reference)->first();
                if ($payment && $response->isSuccessful()) {
                    $payment->markAsPaid($response->transactionId, $response->raw);
                }
            }
        }

        $returnUrl = $request->query('return_url') ?: url('/');

        return redirect($returnUrl)->with('payment_status', 'completed');
    }

    /**
     * Handle customer cancel from offsite payment gateway.
     */
    public function handleCancel(string $gateway, Request $request): RedirectResponse
    {
        $driver = Payment::gateway($gateway);

        if ($driver instanceof RedirectableGatewayInterface) {
            $response = $driver->handleCancel($request);

            if ($response->reference) {
                $payment = PaymentModel::where('reference', $response->reference)->first();
                if ($payment) {
                    $payment->update(['status' => PaymentStatus::CANCELLED]);
                }
            }
        }

        $cancelUrl = $request->query('cancel_url') ?: url('/');

        return redirect($cancelUrl)->with('payment_status', 'cancelled');
    }
}
