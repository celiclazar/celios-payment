<?php

namespace Modules\Payment\Contracts;

use Illuminate\Http\Request;
use Modules\Payment\DTO\PaymentResponse;

interface RedirectableGatewayInterface
{
    /**
     * Handle customer returning from an offsite gateway redirect.
     */
    public function handleReturn(Request $request): PaymentResponse;

    /**
     * Handle customer cancelling from an offsite gateway redirect.
     */
    public function handleCancel(Request $request): PaymentResponse;
}
