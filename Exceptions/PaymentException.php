<?php

namespace Modules\Payment\Exceptions;

use Exception;

class PaymentException extends Exception
{
    public function __construct(
        string $message = '',
        public readonly ?string $errorCode = null,
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
