<?php

namespace Modules\Payment\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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

            if ($response->reference && $response->isSuccessful()) {
                DB::transaction(function () use ($response): void {
                    $payment = PaymentModel::where('reference', $response->reference)
                        ->lockForUpdate()
                        ->first();

                    if ($payment && in_array($payment->status, [PaymentStatus::PENDING, PaymentStatus::ACTION_REQUIRED], true)) {
                        $payment->markAsPaid($response->transactionId, $response->raw);
                    }
                });
            }
        }

        $returnUrl = $this->resolveSafeRedirectUrl($request->query('return_url'));

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
                DB::transaction(function () use ($response): void {
                    $payment = PaymentModel::where('reference', $response->reference)
                        ->lockForUpdate()
                        ->first();

                    // Never downgrade an already finalized (PAID / REFUNDED) payment via a cancel redirect
                    if ($payment && in_array($payment->status, [PaymentStatus::PENDING, PaymentStatus::ACTION_REQUIRED], true)) {
                        $payment->update(['status' => PaymentStatus::CANCELLED]);
                    }
                });
            }
        }

        $cancelUrl = $this->resolveSafeRedirectUrl($request->query('cancel_url'));

        return redirect($cancelUrl)->with('payment_status', 'cancelled');
    }

    /**
     * Prevent open redirects by allowing only local relative paths or explicitly allowlisted hosts.
     */
    protected function resolveSafeRedirectUrl(mixed $candidate): string
    {
        if (! is_string($candidate)) {
            return url('/');
        }

        $candidate = trim($candidate);
        if ($candidate === '' || preg_match('/[\r\n\0\\\\]/', $candidate)) {
            return url('/');
        }

        // Allow safe same-origin relative paths (reject protocol-relative "//evil.com")
        if (str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//')) {
            return url($candidate);
        }

        if (! filter_var($candidate, FILTER_VALIDATE_URL)) {
            return url('/');
        }

        $scheme = strtolower((string) parse_url($candidate, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return url('/');
        }

        $targetHost = strtolower((string) parse_url($candidate, PHP_URL_HOST));
        $appHost = strtolower((string) parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST));
        $allowedHosts = array_filter(array_map(
            static fn ($h): string => strtolower(trim((string) $h)),
            array_merge([$appHost], (array) config('payment.allowed_redirect_hosts', []))
        ));

        if ($targetHost === '' || ! in_array($targetHost, $allowedHosts, true)) {
            return url('/');
        }

        return $candidate;
    }
}
