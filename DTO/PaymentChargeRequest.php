<?php

namespace Modules\Payment\DTO;

use Illuminate\Database\Eloquent\Model;

class PaymentChargeRequest
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $reference = null,
        public readonly ?string $description = null,
        public readonly ?CustomerData $customer = null,
        public readonly ?string $returnUrl = null,
        public readonly ?string $cancelUrl = null,
        public readonly ?string $webhookUrl = null,
        public readonly ?string $paymentMethodToken = null,
        public readonly ?Model $payable = null,
        public readonly array $metadata = [],
        public readonly array $options = [],
    ) {}

    public static function make(float $amount, string $currency): self
    {
        return new self(
            amount: $amount,
            currency: strtoupper($currency),
        );
    }

    public function withReference(string $reference): self
    {
        return new self(
            amount: $this->amount,
            currency: $this->currency,
            reference: $reference,
            description: $this->description,
            customer: $this->customer,
            returnUrl: $this->returnUrl,
            cancelUrl: $this->cancelUrl,
            webhookUrl: $this->webhookUrl,
            paymentMethodToken: $this->paymentMethodToken,
            payable: $this->payable,
            metadata: $this->metadata,
            options: $this->options,
        );
    }

    public function withDescription(string $description): self
    {
        return new self(
            amount: $this->amount,
            currency: $this->currency,
            reference: $this->reference,
            description: $description,
            customer: $this->customer,
            returnUrl: $this->returnUrl,
            cancelUrl: $this->cancelUrl,
            webhookUrl: $this->webhookUrl,
            paymentMethodToken: $this->paymentMethodToken,
            payable: $this->payable,
            metadata: $this->metadata,
            options: $this->options,
        );
    }

    public function withCustomer(CustomerData $customer): self
    {
        return new self(
            amount: $this->amount,
            currency: $this->currency,
            reference: $this->reference,
            description: $this->description,
            customer: $customer,
            returnUrl: $this->returnUrl,
            cancelUrl: $this->cancelUrl,
            webhookUrl: $this->webhookUrl,
            paymentMethodToken: $this->paymentMethodToken,
            payable: $this->payable,
            metadata: $this->metadata,
            options: $this->options,
        );
    }

    public function withUrls(?string $returnUrl, ?string $cancelUrl = null, ?string $webhookUrl = null): self
    {
        return new self(
            amount: $this->amount,
            currency: $this->currency,
            reference: $this->reference,
            description: $this->description,
            customer: $this->customer,
            returnUrl: $returnUrl,
            cancelUrl: $cancelUrl ?? $returnUrl,
            webhookUrl: $webhookUrl,
            paymentMethodToken: $this->paymentMethodToken,
            payable: $this->payable,
            metadata: $this->metadata,
            options: $this->options,
        );
    }

    public function withPaymentMethodToken(string $token): self
    {
        return new self(
            amount: $this->amount,
            currency: $this->currency,
            reference: $this->reference,
            description: $this->description,
            customer: $this->customer,
            returnUrl: $this->returnUrl,
            cancelUrl: $this->cancelUrl,
            webhookUrl: $this->webhookUrl,
            paymentMethodToken: $token,
            payable: $this->payable,
            metadata: $this->metadata,
            options: $this->options,
        );
    }

    public function withPayable(Model $payable): self
    {
        return new self(
            amount: $this->amount,
            currency: $this->currency,
            reference: $this->reference,
            description: $this->description,
            customer: $this->customer,
            returnUrl: $this->returnUrl,
            cancelUrl: $this->cancelUrl,
            webhookUrl: $this->webhookUrl,
            paymentMethodToken: $this->paymentMethodToken,
            payable: $payable,
            metadata: $this->metadata,
            options: $this->options,
        );
    }

    public function withMetadata(array $metadata): self
    {
        return new self(
            amount: $this->amount,
            currency: $this->currency,
            reference: $this->reference,
            description: $this->description,
            customer: $this->customer,
            returnUrl: $this->returnUrl,
            cancelUrl: $this->cancelUrl,
            webhookUrl: $this->webhookUrl,
            paymentMethodToken: $this->paymentMethodToken,
            payable: $this->payable,
            metadata: array_merge($this->metadata, $metadata),
            options: $this->options,
        );
    }

    public function withOptions(array $options): self
    {
        return new self(
            amount: $this->amount,
            currency: $this->currency,
            reference: $this->reference,
            description: $this->description,
            customer: $this->customer,
            returnUrl: $this->returnUrl,
            cancelUrl: $this->cancelUrl,
            webhookUrl: $this->webhookUrl,
            paymentMethodToken: $this->paymentMethodToken,
            payable: $this->payable,
            metadata: $this->metadata,
            options: array_merge($this->options, $options),
        );
    }
}
