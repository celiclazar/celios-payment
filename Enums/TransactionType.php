<?php

namespace Modules\Payment\Enums;

enum TransactionType: string
{
    case CHARGE = 'charge';
    case AUTHORIZE = 'authorize';
    case CAPTURE = 'capture';
    case REFUND = 'refund';
    case VOID = 'void';
    case WEBHOOK = 'webhook';

    public function label(): string
    {
        return match ($this) {
            self::CHARGE => 'Charge',
            self::AUTHORIZE => 'Authorize',
            self::CAPTURE => 'Capture',
            self::REFUND => 'Refund',
            self::VOID => 'Void',
            self::WEBHOOK => 'Webhook',
        };
    }
}
