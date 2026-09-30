<?php

namespace Modules\Payment\DTO;

class CustomerData
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $ipAddress = null,
        public readonly ?AddressData $billingAddress = null,
        public readonly ?AddressData $shippingAddress = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $billing = isset($data['billingAddress']) || isset($data['billing_address'])
            ? AddressData::fromArray($data['billingAddress'] ?? $data['billing_address'])
            : null;

        $shipping = isset($data['shippingAddress']) || isset($data['shipping_address'])
            ? AddressData::fromArray($data['shippingAddress'] ?? $data['shipping_address'])
            : null;

        return new self(
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            ipAddress: $data['ipAddress'] ?? $data['ip'] ?? null,
            billingAddress: $billing,
            shippingAddress: $shipping,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'ip_address' => $this->ipAddress,
            'billing_address' => $this->billingAddress?->toArray(),
            'shipping_address' => $this->shippingAddress?->toArray(),
        ], fn ($val) => ! is_null($val));
    }
}
