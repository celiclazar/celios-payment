<?php

namespace Modules\Payment\DTO;

class RefundResponse
{
    public function __construct(
        public readonly bool $isSuccessful,
        public readonly ?string $refundId = null,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $message = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = [],
    ) {}

    public static function success(
        string $refundId,
        float $amount,
        ?string $currency = null,
        array $raw = [],
        string $message = 'Refund processed successfully'
    ): self {
        return new self(
            isSuccessful: true,
            refundId: $refundId,
            amount: $amount,
            currency: $currency,
            message: $message,
            raw: $raw,
        );
    }

    public static function failed(
        string $errorMessage,
        ?string $errorCode = null,
        array $raw = []
    ): self {
        return new self(
            isSuccessful: false,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            raw: $raw,
        );
    }
}
