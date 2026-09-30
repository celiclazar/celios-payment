<?php

namespace Modules\Payment\Exceptions;

class UnsupportedOperationException extends PaymentException
{
    public static function forOperation(string $gateway, string $operation): self
    {
        return new self("Payment gateway [{$gateway}] does not support the [{$operation}] operation.");
    }
}
