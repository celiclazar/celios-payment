<?php

namespace Modules\Payment\Filament\Resources\PaymentGatewayResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Payment\Filament\Resources\PaymentGatewayResource;

class CreatePaymentGateway extends CreateRecord
{
    protected static string $resource = PaymentGatewayResource::class;
}
