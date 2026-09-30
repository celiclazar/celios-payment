<?php

namespace Modules\Payment\Contracts;

use Modules\Payment\DTO\CustomerData;

interface TokenizableGatewayInterface
{
    /**
     * Create a customer profile on the gateway and return their customer ID.
     */
    public function createCustomer(CustomerData $customer): ?string;

    /**
     * Tokenize and store a payment method under a customer profile.
     */
    public function storePaymentMethod(string $customerId, string $token): ?string;
}
