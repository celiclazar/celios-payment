<?php

namespace Modules\Payment\Contracts;

use Modules\Payment\DTO\PaymentChargeRequest;
use Modules\Payment\DTO\PaymentResponse;

interface AuthorizableGatewayInterface
{
    /**
     * Authorize funds on customer's account without capturing them immediately.
     */
    public function authorize(PaymentChargeRequest $request): PaymentResponse;

    /**
     * Capture previously authorized funds.
     */
    public function capture(string $transactionId, ?float $amount = null): PaymentResponse;

    /**
     * Void/cancel a previous authorization.
     */
    public function void(string $transactionId): PaymentResponse;
}
