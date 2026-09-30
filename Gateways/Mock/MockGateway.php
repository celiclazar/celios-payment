<?php

namespace Modules\Payment\Gateways\Mock;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Payment\Contracts\AuthorizableGatewayInterface;
use Modules\Payment\Contracts\InquirableGatewayInterface;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Contracts\RedirectableGatewayInterface;
use Modules\Payment\Contracts\RefundableGatewayInterface;
use Modules\Payment\Contracts\WebhookGatewayInterface;
use Modules\Payment\DTO\PaymentChargeRequest;
use Modules\Payment\DTO\PaymentResponse;
use Modules\Payment\DTO\RefundRequest;
use Modules\Payment\DTO\RefundResponse;
use Modules\Payment\DTO\WebhookResult;
use Modules\Payment\Enums\PaymentStatus;
use Modules\Payment\Gateways\AbstractGateway;

class MockGateway extends AbstractGateway implements AuthorizableGatewayInterface, InquirableGatewayInterface, PaymentGatewayInterface, RedirectableGatewayInterface, RefundableGatewayInterface, WebhookGatewayInterface
{
    public function getId(): string
    {
        return 'mock';
    }

    public function getName(): string
    {
        return 'Mock / Sandbox Gateway';
    }

    public function charge(PaymentChargeRequest $request): PaymentResponse
    {
        $simulation = $request->options['simulate'] ?? $this->config('simulate', 'success');
        $txId = 'mock_ch_'.Str::random(16);

        return match ($simulation) {
            'fail', 'failure' => PaymentResponse::failed(
                errorMessage: 'Simulated mock payment failure',
                errorCode: 'CARD_DECLINED',
                transactionId: $txId,
                reference: $request->reference,
                raw: ['simulated' => true, 'error' => 'CARD_DECLINED']
            ),
            'action_required', '3ds' => PaymentResponse::actionRequired(
                redirectUrl: $request->returnUrl ?? url('/payments/mock/return?ref='.$request->reference),
                transactionId: $txId,
                reference: $request->reference,
                raw: ['simulated' => true, 'requires_3ds' => true]
            ),
            default => PaymentResponse::success(
                transactionId: $txId,
                reference: $request->reference,
                amount: $request->amount,
                currency: $request->currency,
                raw: ['simulated' => true, 'status' => 'succeeded'],
                message: 'Mock payment charged successfully'
            )
        };
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $simulation = $request->metadata['simulate'] ?? $this->config('simulate_refund', 'success');

        if ($simulation === 'failure') {
            return RefundResponse::failed(
                errorMessage: 'Simulated refund decline',
                errorCode: 'INSUFFICIENT_FUNDS',
                raw: ['simulated' => true]
            );
        }

        return RefundResponse::success(
            refundId: 'mock_re_'.Str::random(16),
            amount: $request->amount,
            currency: $request->currency,
            raw: ['simulated' => true, 'status' => 'refunded']
        );
    }

    public function authorize(PaymentChargeRequest $request): PaymentResponse
    {
        $txId = 'mock_auth_'.Str::random(16);

        return new PaymentResponse(
            status: PaymentStatus::AUTHORIZED,
            transactionId: $txId,
            reference: $request->reference,
            amount: $request->amount,
            currency: $request->currency,
            message: 'Mock funds authorized successfully',
            raw: ['simulated' => true, 'status' => 'authorized']
        );
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResponse
    {
        return PaymentResponse::success(
            transactionId: $transactionId,
            amount: $amount,
            raw: ['simulated' => true, 'captured' => true],
            message: 'Mock authorization captured'
        );
    }

    public function void(string $transactionId): PaymentResponse
    {
        return new PaymentResponse(
            status: PaymentStatus::CANCELLED,
            transactionId: $transactionId,
            message: 'Mock authorization voided',
            raw: ['simulated' => true, 'voided' => true]
        );
    }

    public function handleReturn(Request $request): PaymentResponse
    {
        return PaymentResponse::success(
            transactionId: $request->query('tx_id', 'mock_ret_'.Str::random(12)),
            reference: $request->query('ref'),
            message: 'Mock redirect returned successfully'
        );
    }

    public function handleCancel(Request $request): PaymentResponse
    {
        return new PaymentResponse(
            status: PaymentStatus::CANCELLED,
            reference: $request->query('ref'),
            message: 'Customer cancelled mock payment'
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        // Accept unless explicitly simulating bad signature
        return $request->header('X-Mock-Signature') !== 'invalid';
    }

    public function processWebhook(Request $request): WebhookResult
    {
        $event = $request->input('event', 'payment.succeeded');
        $txId = $request->input('transaction_id', 'mock_tx_'.Str::random(12));
        $ref = $request->input('reference');
        $amount = (float) $request->input('amount', 0);

        return WebhookResult::handled(
            eventType: $event,
            gatewayTransactionId: $txId,
            paymentReference: $ref,
            newStatus: PaymentStatus::PAID,
            amount: $amount,
            payload: $request->all()
        );
    }

    public function inquire(string $gatewayTransactionId): PaymentResponse
    {
        return PaymentResponse::success(
            transactionId: $gatewayTransactionId,
            message: 'Mock inquiry returned success'
        );
    }
}
