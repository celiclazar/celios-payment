<?php

namespace Modules\Payment\Contracts;

use Modules\Payment\DTO\PaymentResponse;

interface InquirableGatewayInterface
{
    /**
     * Inquire/fetch the current transaction status directly from the gateway API.
     */
    public function inquire(string $gatewayTransactionId): PaymentResponse;
}
