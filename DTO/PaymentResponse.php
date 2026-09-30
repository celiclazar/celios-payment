<?php

namespace Modules\Payment\DTO;

use Modules\Payment\Enums\ActionType;
use Modules\Payment\Enums\PaymentStatus;

class PaymentResponse
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $reference = null,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?ActionType $actionType = null,
        public readonly array $actionData = [],
        public readonly ?string $message = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = [],
    ) {}

    public static function success(
        string $transactionId,
        ?string $reference = null,
        ?float $amount = null,
        ?string $currency = null,
        array $raw = [],
        ?string $message = 'Payment completed successfully'
    ): self {
        return new self(
            status: PaymentStatus::PAID,
            transactionId: $transactionId,
            reference: $reference,
            amount: $amount,
            currency: $currency,
            message: $message,
            raw: $raw,
        );
    }

    public static function pending(
        string $transactionId,
        ?string $reference = null,
        ?float $amount = null,
        ?string $currency = null,
        ?string $message = 'Payment is pending',
        array $actionData = [],
        array $raw = []
    ): self {
        return new self(
            status: PaymentStatus::PENDING,
            transactionId: $transactionId,
            reference: $reference,
            amount: $amount,
            currency: $currency,
            actionType: ! empty($actionData) ? ActionType::DISPLAY_INSTRUCTIONS : null,
            actionData: $actionData,
            message: $message,
            raw: $raw,
        );
    }

    public static function actionRequired(
        string $redirectUrl,
        ?string $transactionId = null,
        ?string $reference = null,
        ActionType $actionType = ActionType::REDIRECT,
        array $actionData = [],
        array $raw = []
    ): self {
        return new self(
            status: PaymentStatus::ACTION_REQUIRED,
            transactionId: $transactionId,
            reference: $reference,
            redirectUrl: $redirectUrl,
            actionType: $actionType,
            actionData: $actionData,
            message: 'Customer action required to finalize payment',
            raw: $raw,
        );
    }

    public static function failed(
        string $errorMessage,
        ?string $errorCode = null,
        ?string $transactionId = null,
        ?string $reference = null,
        array $raw = []
    ): self {
        return new self(
            status: PaymentStatus::FAILED,
            transactionId: $transactionId,
            reference: $reference,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            raw: $raw,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isActionRequired(): bool
    {
        return $this->status === PaymentStatus::ACTION_REQUIRED;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }
}
