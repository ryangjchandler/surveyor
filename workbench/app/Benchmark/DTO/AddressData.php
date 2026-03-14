<?php

namespace App\Benchmark\DTO;

class AddressData
{
    public function __construct(
        public readonly string $line1,
        public readonly ?string $line2,
        public readonly string $city,
        public readonly string $postalCode,
        public readonly string $country,
    ) {}

    /**
     * @return array{line1: string, line2: string|null, city: string, postal_code: string, country: string}
     */
    public function toPayload(): array
    {
        return [
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
        ];
    }
}
