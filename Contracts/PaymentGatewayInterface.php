<?php

namespace Modules\Payment\Contracts;

use Modules\Payment\DTO\PaymentChargeRequest;
use Modules\Payment\DTO\PaymentResponse;

interface PaymentGatewayInterface
{
    /**
     * Unique identifier code for this gateway (e.g. 'stripe', 'paypal', 'bank_transfer').
     */
    public function getId(): string;

    /**
     * Human-readable name for the gateway (e.g. 'Stripe', 'Bank Wire Transfer').
     */
    public function getName(): string;

    /**
     * Check if the gateway is configured and ready to accept payments.
     */
    public function isAvailable(): bool;

    /**
     * List of ISO currency codes supported by this gateway (e.g. ['USD', 'EUR', 'RSD']).
     * An empty array indicates all currencies are supported.
     */
    public function getSupportedCurrencies(): array;

    /**
     * Process an immediate payment charge.
     */
    public function charge(PaymentChargeRequest $request): PaymentResponse;
}
