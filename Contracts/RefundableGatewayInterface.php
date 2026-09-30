<?php

namespace Modules\Payment\Contracts;

use Modules\Payment\DTO\RefundRequest;
use Modules\Payment\DTO\RefundResponse;

interface RefundableGatewayInterface
{
    /**
     * Process a full or partial refund for a previous transaction.
     */
    public function refund(RefundRequest $request): RefundResponse;
}
