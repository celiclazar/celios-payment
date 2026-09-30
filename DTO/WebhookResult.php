<?php

namespace Modules\Payment\DTO;

use Modules\Payment\Enums\PaymentStatus;

class WebhookResult
{
    public function __construct(
        public readonly bool $isHandled,
        public readonly string $eventType,
        public readonly ?string $gatewayTransactionId = null,
        public readonly ?string $paymentReference = null,
        public readonly ?PaymentStatus $newStatus = null,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $message = null,
        public readonly array $payload = [],
    ) {}

    public static function handled(
        string $eventType,
        ?string $gatewayTransactionId = null,
        ?string $paymentReference = null,
        ?PaymentStatus $newStatus = null,
        ?float $amount = null,
        ?string $currency = null,
        array $payload = [],
        ?string $message = 'Webhook processed'
    ): self {
        return new self(
            isHandled: true,
            eventType: $eventType,
            gatewayTransactionId: $gatewayTransactionId,
            paymentReference: $paymentReference,
            newStatus: $newStatus,
            amount: $amount,
            currency: $currency,
            message: $message,
            payload: $payload,
        );
    }

    public static function ignored(string $eventType, ?string $message = 'Event ignored', array $payload = []): self
    {
        return new self(
            isHandled: true,
            eventType: $eventType,
            message: $message,
            payload: $payload,
        );
    }

    public static function failed(string $eventType, string $errorMessage, array $payload = []): self
    {
        return new self(
            isHandled: false,
            eventType: $eventType,
            message: $errorMessage,
            payload: $payload,
        );
    }
}
