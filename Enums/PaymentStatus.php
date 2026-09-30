<?php

namespace Modules\Payment\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case ACTION_REQUIRED = 'action_required';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::AUTHORIZED => 'Authorized',
            self::PROCESSING => 'Processing',
            self::PAID => 'Paid',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
            self::ACTION_REQUIRED => 'Action Required',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PAID => 'success',
            self::AUTHORIZED => 'info',
            self::PROCESSING, self::ACTION_REQUIRED => 'warning',
            self::PENDING => 'gray',
            self::FAILED, self::CANCELLED => 'danger',
            self::REFUNDED, self::PARTIALLY_REFUNDED => 'purple',
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::PAID, self::AUTHORIZED]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::PAID, self::FAILED, self::CANCELLED, self::REFUNDED]);
    }
}
