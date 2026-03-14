<?php

namespace App\Benchmark\DTO;

use App\Benchmark\Enums\Channel;
use Carbon\CarbonImmutable;

/**
 * @property-read string $reference
 * @property-read Channel $channel
 *
 * @method bool isExpress()
 */
class ShipmentData
{
    public function __construct(
        public readonly string $reference,
        public readonly AddressData $address,
        public readonly Channel $channel,
        public readonly bool $express,
        public readonly CarbonImmutable $shipAt,
    ) {}

    public function isExpress(): bool
    {
        return $this->express;
    }

    /**
     * @return array{reference: string, address: array<string, mixed>, channel: string, express: bool, ship_at: string}
     */
    public function toPayload(): array
    {
        return [
            'reference' => $this->reference,
            'address' => $this->address->toPayload(),
            'channel' => $this->channel->value,
            'express' => $this->express,
            'ship_at' => $this->shipAt->toIso8601String(),
        ];
    }
}
