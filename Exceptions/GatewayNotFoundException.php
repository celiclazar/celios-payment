<?php

namespace Modules\Payment\Exceptions;

class GatewayNotFoundException extends PaymentException
{
    public static function forGateway(string $gateway): self
    {
        return new self("Payment gateway driver [{$gateway}] is not registered or supported.");
    }
}
