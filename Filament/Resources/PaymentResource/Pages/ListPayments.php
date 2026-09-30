<?php

namespace Modules\Payment\Filament\Resources\PaymentResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Payment\Filament\Resources\PaymentResource;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
