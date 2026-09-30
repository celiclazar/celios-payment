<?php

namespace Modules\Payment\DTO;

class AddressData
{
    public function __construct(
        public readonly ?string $line1 = null,
        public readonly ?string $line2 = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $country = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            line1: $data['line1'] ?? $data['street'] ?? $data['address'] ?? null,
            line2: $data['line2'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? $data['province'] ?? null,
            postalCode: $data['postalCode'] ?? $data['postal_code'] ?? $data['zip'] ?? null,
            country: $data['country'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
        ], fn ($val) => ! is_null($val));
    }
}
