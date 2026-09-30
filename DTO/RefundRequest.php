<?php

namespace Modules\Payment\DTO;

class RefundRequest
{
    public function __construct(
        public readonly string $gatewayTransactionId,
        public readonly float $amount,
        public readonly ?string $currency = null,
        public readonly ?string $reason = null,
        public readonly ?string $paymentReference = null,
        public readonly array $metadata = [],
    ) {}

    public static function make(string $gatewayTransactionId, float $amount): self
    {
        return new self(
            gatewayTransactionId: $gatewayTransactionId,
            amount: $amount,
        );
    }

    public function withReason(string $reason): self
    {
        return new self(
            gatewayTransactionId: $this->gatewayTransactionId,
            amount: $this->amount,
            currency: $this->currency,
            reason: $reason,
            paymentReference: $this->paymentReference,
            metadata: $this->metadata,
        );
    }

    public function withCurrency(string $currency): self
    {
        return new self(
            gatewayTransactionId: $this->gatewayTransactionId,
            amount: $this->amount,
            currency: strtoupper($currency),
            reason: $this->reason,
            paymentReference: $this->paymentReference,
            metadata: $this->metadata,
        );
    }

    public function withMetadata(array $metadata): self
    {
        return new self(
            gatewayTransactionId: $this->gatewayTransactionId,
            amount: $this->amount,
            currency: $this->currency,
            reason: $this->reason,
            paymentReference: $this->paymentReference,
            metadata: array_merge($this->metadata, $metadata),
        );
    }
}
