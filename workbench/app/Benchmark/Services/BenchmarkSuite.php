<?php

namespace App\Benchmark\Services;

use App\Benchmark\DTO\AddressData;
use App\Benchmark\DTO\ShipmentData;
use App\Benchmark\Enums\Channel;
use App\Benchmark\Support\MacroRegistry;
use App\Benchmark\Support\ProcessingEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class BenchmarkSuite
{
    /**
     * @return array<string, mixed>
     */
    public function runSample(): array
    {
        MacroRegistry::register();

        $address = new AddressData(
            line1: '123 Benchmark Road',
            line2: null,
            city: 'Example City',
            postalCode: '12345',
            country: 'US',
        );

        $shipment = new ShipmentData(
            reference: 'ORD-'.Str::random(8),
            address: $address,
            channel: Channel::Web,
            express: true,
            shipAt: CarbonImmutable::now(),
        );

        $engine = new ProcessingEngine;

        return $engine->process([
            $shipment,
        ], fn (ShipmentData $item): array => [
            ...$item->toPayload(),
            'reference_slug' => Str::slug($item->reference),
        ]);
    }
}
