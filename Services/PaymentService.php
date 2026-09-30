<?php

namespace Modules\Payment\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Payment\Contracts\AuthorizableGatewayInterface;
use Modules\Payment\Contracts\InquirableGatewayInterface;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Contracts\RefundableGatewayInterface;
use Modules\Payment\Contracts\WebhookGatewayInterface;
use Modules\Payment\DTO\PaymentChargeRequest;
use Modules\Payment\DTO\PaymentResponse;
use Modules\Payment\DTO\RefundRequest;
use Modules\Payment\DTO\RefundResponse;
use Modules\Payment\DTO\WebhookResult;
use Modules\Payment\Enums\PaymentStatus;
use Modules\Payment\Enums\TransactionType;
use Modules\Payment\Events\PaymentCreated;
use Modules\Payment\Events\PaymentFailed;
use Modules\Payment\Events\PaymentProcessing;
use Modules\Payment\Events\PaymentRefunded;
use Modules\Payment\Events\PaymentSucceeded;
use Modules\Payment\Events\PaymentWebhookReceived;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Exceptions\UnsupportedOperationException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentTransaction;

class PaymentService
{
    public function __construct(
        protected PaymentManager $manager
    ) {}

    /**
     * Get the underlying PaymentManager instance.
     */
    public function getManager(): PaymentManager
    {
        return $this->manager;
    }

    /**
     * Get a specific gateway driver.
     */
    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        return $this->manager->driver($name);
    }

    /**
     * Process an immediate charge through a payment gateway.
     */
    public function charge(PaymentChargeRequest $request, ?string $gateway = null): PaymentResponse
    {
        $driver = $this->gateway($gateway);
        $gatewayId = $driver->getId();

        $reference = $request->reference ?: 'PAY-'.strtoupper(Str::random(12));
        $chargeRequest = $request->reference ? $request : $request->withReference($reference);

        // 1. Create Payment record in DB
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'payable_type' => $chargeRequest->payable ? get_class($chargeRequest->payable) : null,
            'payable_id' => $chargeRequest->payable?->getKey(),
            'gateway' => $gatewayId,
            'reference' => $reference,
            'amount' => $chargeRequest->amount,
            'currency' => $chargeRequest->currency,
            'status' => PaymentStatus::PENDING,
            'description' => $chargeRequest->description,
            'customer_name' => $chargeRequest->customer?->name,
            'customer_email' => $chargeRequest->customer?->email,
            'customer_phone' => $chargeRequest->customer?->phone,
            'customer_ip' => $chargeRequest->customer?->ipAddress,
            'billing_address' => $chargeRequest->customer?->billingAddress?->toArray(),
            'shipping_address' => $chargeRequest->customer?->shippingAddress?->toArray(),
            'metadata' => $chargeRequest->metadata,
        ]);

        event(new PaymentCreated($payment));
        event(new PaymentProcessing($payment));

        try {
            // 2. Delegate to Gateway Driver
            $response = $driver->charge($chargeRequest);

            // 3. Record Transaction Ledger Entry
            PaymentTransaction::create([
                'payment_id' => $payment->id,
                'type' => TransactionType::CHARGE,
                'gateway_reference' => $response->transactionId,
                'amount' => $chargeRequest->amount,
                'currency' => $chargeRequest->currency,
                'status' => $response->status->value,
                'payload' => $response->raw,
                'error_message' => $response->errorMessage,
            ]);

            // 4. Update Payment status according to driver response
            if ($response->isSuccessful()) {
                $payment->markAsPaid($response->transactionId, $response->raw);
                event(new PaymentSucceeded($payment, $response));
            } elseif ($response->isFailed()) {
                $payment->markAsFailed($response->errorMessage ?? 'Payment failed', $response->transactionId, $response->raw);
                event(new PaymentFailed($payment, $response));
            } else {
                // Pending or Action Required
                $payment->update([
                    'status' => $response->status,
                    'gateway_transaction_id' => $response->transactionId ?? $payment->gateway_transaction_id,
                    'raw_response' => $response->raw,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            $payment->markAsFailed($e->getMessage());

            PaymentTransaction::create([
                'payment_id' => $payment->id,
                'type' => TransactionType::CHARGE,
                'amount' => $chargeRequest->amount,
                'currency' => $chargeRequest->currency,
                'status' => PaymentStatus::FAILED->value,
                'error_message' => $e->getMessage(),
            ]);

            event(new PaymentFailed($payment));

            throw new PaymentException("Payment charge failed: {$e->getMessage()}", 'CHARGE_FAILED', [
                'payment_id' => $payment->id,
                'reference' => $reference,
            ], 0, $e);
        }
    }

    /**
     * Process a refund for a previously paid transaction.
     */
    public function refund(Payment|string $payment, float $amount, ?string $reason = null): RefundResponse
    {
        $payment = is_string($payment)
            ? Payment::where('reference', $payment)->orWhere('uuid', $payment)->firstOrFail()
            : $payment;

        if (! $payment->isPaid() && ! $payment->isRefunded()) {
            throw new PaymentException("Cannot refund a payment with status [{$payment->status->value}].");
        }

        $remainingAmount = $payment->getRemainingRefundableAmount();
        if ($amount > $remainingAmount) {
            throw new PaymentException("Refund amount ({$amount}) exceeds remaining refundable amount ({$remainingAmount}).");
        }

        $driver = $this->gateway($payment->gateway);

        if (! $driver instanceof RefundableGatewayInterface) {
            throw UnsupportedOperationException::forOperation($payment->gateway, 'refund');
        }

        $refundRequest = RefundRequest::make($payment->gateway_transaction_id ?: $payment->reference, $amount)
            ->withCurrency($payment->currency)
            ->withReason($reason ?? 'Customer requested refund')
            ->withMetadata(['payment_reference' => $payment->reference]);

        $response = $driver->refund($refundRequest);

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => TransactionType::REFUND,
            'gateway_reference' => $response->refundId,
            'amount' => $amount,
            'currency' => $payment->currency,
            'status' => $response->isSuccessful ? 'success' : 'failed',
            'payload' => $response->raw,
            'error_message' => $response->errorMessage,
        ]);

        if ($response->isSuccessful) {
            $payment->markAsRefunded($amount);
            event(new PaymentRefunded($payment, $amount, $response));
        }

        return $response;
    }

    /**
     * Authorize funds without capturing them immediately.
     */
    public function authorize(PaymentChargeRequest $request, ?string $gateway = null): PaymentResponse
    {
        $driver = $this->gateway($gateway);

        if (! $driver instanceof AuthorizableGatewayInterface) {
            throw UnsupportedOperationException::forOperation($driver->getId(), 'authorize');
        }

        $reference = $request->reference ?: 'AUTH-'.strtoupper(Str::random(12));
        $authRequest = $request->reference ? $request : $request->withReference($reference);

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'payable_type' => $authRequest->payable ? get_class($authRequest->payable) : null,
            'payable_id' => $authRequest->payable?->getKey(),
            'gateway' => $driver->getId(),
            'reference' => $reference,
            'amount' => $authRequest->amount,
            'currency' => $authRequest->currency,
            'status' => PaymentStatus::PENDING,
            'description' => $authRequest->description,
            'customer_name' => $authRequest->customer?->name,
            'customer_email' => $authRequest->customer?->email,
            'metadata' => $authRequest->metadata,
        ]);

        $response = $driver->authorize($authRequest);

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => TransactionType::AUTHORIZE,
            'gateway_reference' => $response->transactionId,
            'amount' => $authRequest->amount,
            'currency' => $authRequest->currency,
            'status' => $response->status->value,
            'payload' => $response->raw,
            'error_message' => $response->errorMessage,
        ]);

        $payment->update([
            'status' => $response->status,
            'gateway_transaction_id' => $response->transactionId,
            'raw_response' => $response->raw,
        ]);

        return $response;
    }

    /**
     * Capture previously authorized payment.
     */
    public function capture(Payment|string $payment, ?float $amount = null): PaymentResponse
    {
        $payment = is_string($payment)
            ? Payment::where('reference', $payment)->orWhere('uuid', $payment)->firstOrFail()
            : $payment;

        if ($payment->status !== PaymentStatus::AUTHORIZED) {
            throw new PaymentException('Only payments with status [authorized] can be captured.');
        }

        $driver = $this->gateway($payment->gateway);

        if (! $driver instanceof AuthorizableGatewayInterface) {
            throw UnsupportedOperationException::forOperation($payment->gateway, 'capture');
        }

        $captureAmount = $amount ?? (float) $payment->amount;
        $response = $driver->capture($payment->gateway_transaction_id, $captureAmount);

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => TransactionType::CAPTURE,
            'gateway_reference' => $response->transactionId,
            'amount' => $captureAmount,
            'currency' => $payment->currency,
            'status' => $response->isSuccessful() ? 'success' : 'failed',
            'payload' => $response->raw,
            'error_message' => $response->errorMessage,
        ]);

        if ($response->isSuccessful()) {
            $payment->markAsPaid($response->transactionId, $response->raw);
            event(new PaymentSucceeded($payment, $response));
        }

        return $response;
    }

    /**
     * Inquire/verify transaction status with the provider API.
     */
    public function inquire(Payment|string $payment): PaymentResponse
    {
        $payment = is_string($payment)
            ? Payment::where('reference', $payment)->orWhere('uuid', $payment)->firstOrFail()
            : $payment;

        $driver = $this->gateway($payment->gateway);

        if (! $driver instanceof InquirableGatewayInterface) {
            throw UnsupportedOperationException::forOperation($payment->gateway, 'inquire');
        }

        return $driver->inquire($payment->gateway_transaction_id ?: $payment->reference);
    }

    /**
     * Process an incoming webhook for a specific gateway.
     */
    public function handleWebhook(string $gateway, Request $request): WebhookResult
    {
        $driver = $this->gateway($gateway);

        if (! $driver instanceof WebhookGatewayInterface) {
            throw UnsupportedOperationException::forOperation($gateway, 'webhook');
        }

        if (! $driver->verifyWebhook($request)) {
            return WebhookResult::failed('verification_failed', 'Webhook signature verification failed');
        }

        $result = $driver->processWebhook($request);

        event(new PaymentWebhookReceived($gateway, $request, $result));

        // If the webhook matched a payment and gave a new status, update it
        if ($result->isHandled && $result->newStatus) {
            $payment = null;

            if ($result->paymentReference) {
                $payment = Payment::where('reference', $result->paymentReference)->first();
            }

            if (! $payment && $result->gatewayTransactionId) {
                $payment = Payment::where('gateway_transaction_id', $result->gatewayTransactionId)->first();
            }

            if ($payment) {
                PaymentTransaction::create([
                    'payment_id' => $payment->id,
                    'type' => TransactionType::WEBHOOK,
                    'gateway_reference' => $result->gatewayTransactionId,
                    'amount' => $result->amount ?? (float) $payment->amount,
                    'currency' => $result->currency ?? $payment->currency,
                    'status' => $result->newStatus->value,
                    'payload' => $result->payload,
                ]);

                if ($result->newStatus === PaymentStatus::PAID && ! $payment->isPaid()) {
                    $payment->markAsPaid($result->gatewayTransactionId, $result->payload);
                    event(new PaymentSucceeded($payment));
                } elseif ($result->newStatus === PaymentStatus::FAILED) {
                    $payment->markAsFailed($result->message ?? 'Failed via webhook', $result->gatewayTransactionId, $result->payload);
                    event(new PaymentFailed($payment));
                } elseif ($result->newStatus === PaymentStatus::REFUNDED && ! $payment->isRefunded()) {
                    $payment->markAsRefunded($result->amount ?? (float) $payment->amount);
                } else {
                    $payment->update(['status' => $result->newStatus]);
                }
            }
        }

        return $result;
    }
}
