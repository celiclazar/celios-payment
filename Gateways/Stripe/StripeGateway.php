<?php

namespace Modules\Payment\Gateways\Stripe;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
use Modules\Payment\Gateways\AbstractGateway;

class StripeGateway extends AbstractGateway implements InquirableGatewayInterface, PaymentGatewayInterface, RefundableGatewayInterface, WebhookGatewayInterface
{
    protected string $baseUrl = 'https://api.stripe.com/v1';

    /**
     * Currencies where Stripe expects whole major units (no *100 multiplier).
     *
     * @var array<int, string>
     */
    protected const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    protected const ALLOWED_REFUND_REASONS = [
        'duplicate',
        'fraudulent',
        'requested_by_customer',
    ];

    public function getId(): string
    {
        return 'stripe';
    }

    public function getName(): string
    {
        return 'Stripe';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->getSecretKey());
    }

    protected function getSecretKey(): ?string
    {
        return $this->config('secret_key', config('payment.gateways.stripe.secret_key'));
    }

    protected function getWebhookSecret(): ?string
    {
        return $this->config('webhook_secret', config('payment.gateways.stripe.webhook_secret'));
    }

    protected function toSmallestUnit(float $amount, string $currency): int
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }

    protected function fromSmallestUnit(int|float $amount, string $currency): float
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (float) $amount;
        }

        return ((float) $amount) / 100;
    }

    protected function mapRefundReason(?string $reason): ?string
    {
        if ($reason === null || trim($reason) === '') {
            return null;
        }

        $normalized = strtolower(trim($reason));

        if (in_array($normalized, self::ALLOWED_REFUND_REASONS, true)) {
            return $normalized;
        }

        if (str_contains($normalized, 'duplicate')) {
            return 'duplicate';
        }

        if (str_contains($normalized, 'fraud')) {
            return 'fraudulent';
        }

        return 'requested_by_customer';
    }

    public function charge(PaymentChargeRequest $request): PaymentResponse
    {
        $secretKey = $this->getSecretKey();

        if (empty($secretKey)) {
            return PaymentResponse::failed('Stripe secret key is not configured.');
        }

        $amountInSmallestUnit = $this->toSmallestUnit($request->amount, $request->currency);

        $params = [
            'amount' => $amountInSmallestUnit,
            'currency' => strtolower($request->currency),
            'description' => $request->description ?? "Payment for {$request->reference}",
            'metadata' => array_merge([
                'payment_reference' => $request->reference,
            ], $request->metadata),
        ];

        if ($request->customer?->email) {
            $params['receipt_email'] = $request->customer->email;
        }

        if ($request->paymentMethodToken) {
            $params['payment_method'] = $request->paymentMethodToken;
            $params['confirm'] = 'true';
            $params['automatic_payment_methods'] = [
                'enabled' => 'true',
                'allow_redirects' => 'never',
            ];
        } else {
            $params['automatic_payment_methods'] = ['enabled' => 'true'];
        }

        if ($request->returnUrl) {
            $params['return_url'] = $request->returnUrl;
        }

        try {
            $response = Http::withToken($secretKey)
                ->asForm()
                ->post("{$this->baseUrl}/payment_intents", $params);

            $data = $response->json();

            if ($response->failed()) {
                $errorMsg = $data['error']['message'] ?? 'Stripe payment intent creation failed.';
                $errorCode = $data['error']['code'] ?? 'STRIPE_ERROR';

                return PaymentResponse::failed(
                    errorMessage: $errorMsg,
                    errorCode: $errorCode,
                    reference: $request->reference,
                    raw: $data ?? []
                );
            }

            $stripeStatus = $data['status'] ?? '';
            $txId = $data['id'] ?? '';

            if ($stripeStatus === 'succeeded') {
                return PaymentResponse::success(
                    transactionId: $txId,
                    reference: $request->reference,
                    amount: $request->amount,
                    currency: $request->currency,
                    raw: $data
                );
            }

            if ($stripeStatus === 'requires_action' && ! empty($data['next_action']['redirect_to_url']['url'])) {
                return PaymentResponse::actionRequired(
                    redirectUrl: $data['next_action']['redirect_to_url']['url'],
                    transactionId: $txId,
                    reference: $request->reference,
                    actionData: ['client_secret' => $data['client_secret'] ?? null],
                    raw: $data
                );
            }

            return PaymentResponse::pending(
                transactionId: $txId,
                reference: $request->reference,
                amount: $request->amount,
                currency: $request->currency,
                actionData: ['client_secret' => $data['client_secret'] ?? null],
                raw: $data
            );
        } catch (Exception $e) {
            return PaymentResponse::failed($e->getMessage(), 'EXCEPTION', null, $request->reference);
        }
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $secretKey = $this->getSecretKey();

        if (empty($secretKey)) {
            return RefundResponse::failed('Stripe secret key is not configured.');
        }

        $metadata = $request->metadata;
        if ($request->reason) {
            $metadata['refund_reason_note'] = $request->reason;
        }

        $params = [
            'payment_intent' => $request->gatewayTransactionId,
            'amount' => $this->toSmallestUnit($request->amount, $request->currency ?? 'EUR'),
            'metadata' => $metadata,
        ];

        $mappedReason = $this->mapRefundReason($request->reason);
        if ($mappedReason !== null) {
            $params['reason'] = $mappedReason;
        }

        try {
            $response = Http::withToken($secretKey)
                ->asForm()
                ->post("{$this->baseUrl}/refunds", $params);

            $data = $response->json();

            if ($response->failed()) {
                return RefundResponse::failed(
                    errorMessage: $data['error']['message'] ?? 'Stripe refund failed.',
                    errorCode: $data['error']['code'] ?? 'STRIPE_REFUND_ERROR',
                    raw: $data ?? []
                );
            }

            return RefundResponse::success(
                refundId: $data['id'],
                amount: $request->amount,
                currency: $request->currency,
                raw: $data
            );
        } catch (Exception $e) {
            return RefundResponse::failed($e->getMessage());
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $webhookSecret = $this->getWebhookSecret();

        // Always fail closed if webhook secret is not configured
        if (empty($webhookSecret)) {
            return false;
        }

        $sigHeader = $request->header('Stripe-Signature');
        if (empty($sigHeader)) {
            return false;
        }

        $payload = $request->getContent();
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $sigHeader) as $part) {
            $item = explode('=', trim($part), 2);
            if (count($item) === 2) {
                if ($item[0] === 't') {
                    $timestamp = $item[1];
                }
                if ($item[0] === 'v1' && $item[1] !== '') {
                    $signatures[] = $item[1];
                }
            }
        }

        if (! $timestamp || ! ctype_digit($timestamp) || empty($signatures)) {
            return false;
        }

        // Prevent replay attacks older than 5 minutes
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function processWebhook(Request $request): WebhookResult
    {
        $data = $request->all();
        $eventId = isset($data['id']) && is_string($data['id']) ? $data['id'] : null;
        $eventType = $data['type'] ?? 'unknown';
        $object = $data['data']['object'] ?? [];

        $txId = $object['id'] ?? null;
        $reference = $object['metadata']['payment_reference'] ?? null;
        $currency = isset($object['currency']) ? strtoupper($object['currency']) : null;
        $amount = isset($object['amount']) ? $this->fromSmallestUnit((float) $object['amount'], $currency ?? 'EUR') : null;

        return match ($eventType) {
            'payment_intent.succeeded' => WebhookResult::handled(
                eventType: $eventType,
                gatewayTransactionId: $txId,
                paymentReference: $reference,
                newStatus: PaymentStatus::PAID,
                amount: $amount,
                currency: $currency,
                payload: $data,
                message: 'Stripe payment succeeded',
                eventId: $eventId,
            ),
            'payment_intent.payment_failed' => WebhookResult::handled(
                eventType: $eventType,
                gatewayTransactionId: $txId,
                paymentReference: $reference,
                newStatus: PaymentStatus::FAILED,
                amount: $amount,
                currency: $currency,
                payload: $data,
                message: $object['last_payment_error']['message'] ?? 'Stripe payment failed',
                eventId: $eventId,
            ),
            'charge.refunded' => WebhookResult::handled(
                eventType: $eventType,
                gatewayTransactionId: $object['payment_intent'] ?? $txId,
                paymentReference: $reference,
                newStatus: PaymentStatus::REFUNDED,
                amount: isset($object['amount_refunded']) ? $this->fromSmallestUnit((float) $object['amount_refunded'], $currency ?? 'EUR') : null,
                currency: $currency,
                payload: $data,
                message: 'Stripe charge refunded',
                eventId: $eventId,
            ),
            default => WebhookResult::ignored(
                eventType: $eventType,
                message: "Stripe event [{$eventType}] ignored",
                payload: $data,
                eventId: $eventId,
            )
        };
    }

    public function inquire(string $gatewayTransactionId): PaymentResponse
    {
        $secretKey = $this->getSecretKey();

        if (empty($secretKey)) {
            return PaymentResponse::failed('Stripe secret key is not configured.');
        }

        try {
            $response = Http::withToken($secretKey)
                ->get("{$this->baseUrl}/payment_intents/{$gatewayTransactionId}");

            $data = $response->json();

            if ($response->failed()) {
                return PaymentResponse::failed(
                    errorMessage: $data['error']['message'] ?? 'Stripe inquiry failed.',
                    transactionId: $gatewayTransactionId,
                    raw: $data
                );
            }

            $status = match ($data['status'] ?? '') {
                'succeeded' => PaymentStatus::PAID,
                'requires_action' => PaymentStatus::ACTION_REQUIRED,
                'canceled' => PaymentStatus::CANCELLED,
                default => PaymentStatus::PENDING,
            };

            $currency = strtoupper($data['currency'] ?? 'EUR');

            return new PaymentResponse(
                status: $status,
                transactionId: $data['id'],
                reference: $data['metadata']['payment_reference'] ?? null,
                amount: $this->fromSmallestUnit((float) ($data['amount'] ?? 0), $currency),
                currency: $currency,
                raw: $data
            );
        } catch (Exception $e) {
            return PaymentResponse::failed($e->getMessage(), 'EXCEPTION', $gatewayTransactionId);
        }
    }
}
