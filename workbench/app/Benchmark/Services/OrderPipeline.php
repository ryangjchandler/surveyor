<?php

namespace App\Benchmark\Services;

use App\Benchmark\DTO\AddressData;
use App\Benchmark\DTO\ShipmentData;
use App\Benchmark\Enums\Channel;
use App\Benchmark\Enums\LineItemType;
use App\Benchmark\Enums\OrderStatus;
use App\Benchmark\Enums\ShipmentCarrier;
use App\Benchmark\Models\Order;
use App\Benchmark\Models\OrderItem;
use App\Benchmark\Models\Shipment;
use App\Benchmark\Support\ArrayBlueprint;
use App\Benchmark\Support\RuleFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

class OrderPipeline
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{status: string, payload: array<string, mixed>, totals: array<string, int|float>, line_item_count: int, carrier: string}
     */
    public function build(array $input): array
    {
        $merged = ArrayBlueprint::merge($input);

        $validator = Validator::make(
            $merged,
            RuleFactory::create(Channel::from($merged['channel'] ?? 'web')),
        );

        $validated = $validator->validate();

        $order = new Order;
        $order->reference = $validated['reference'];
        $order->channel = Channel::from($validated['channel']);
        $order->status = OrderStatus::Draft;
        $order->meta_data = [
            'source' => 'benchmark',
            'priority' => $validated['priority'] ?? 'normal',
        ];
        $order->total_cents = $this->calculateTotal($validated['items']);

        if (($validated['priority'] ?? null) === 'high') {
            $order->transitionTo(OrderStatus::Pending);
            $order->transitionTo(OrderStatus::Paid);
        } elseif (isset($validated['manual_review']) && $validated['manual_review']) {
            $order->transitionTo(OrderStatus::Canceled);
        } else {
            $order->transitionTo(OrderStatus::Pending);
        }

        $address = new AddressData(
            line1: (string) ($validated['shipping']['line1'] ?? 'Unknown'),
            line2: $validated['shipping']['line2'] ?? null,
            city: (string) ($validated['shipping']['city'] ?? 'Unknown'),
            postalCode: (string) ($validated['shipping']['postal_code'] ?? '00000'),
            country: (string) ($validated['shipping']['country'] ?? 'US'),
        );

        $shipment = new ShipmentData(
            reference: $order->reference,
            address: $address,
            channel: Channel::from($validated['channel']),
            express: (bool) ($validated['express'] ?? false),
            shipAt: CarbonImmutable::now()->addDays(1),
        );

        $shipmentModel = new Shipment;
        $shipmentModel->carrier = ShipmentCarrier::Ups;
        $shipmentModel->tracking_number = 'TRK-'.strtoupper(substr($order->reference, 0, 6));
        $shipmentModel->meta_data = [
            'express' => $shipment->isExpress(),
            'ship_at' => $shipment->shipAt->toIso8601String(),
        ];

        $lineItems = array_map(function (array $item): OrderItem {
            $line = new OrderItem;
            $line->sku = $item['sku'];
            $line->quantity = (int) $item['quantity'];
            $line->item_type = LineItemType::Product;
            $line->price_cents = $item['price'];

            return $line;
        }, $validated['items']);

        $ratio = $order->total_cents > 0
            ? round($order->total_cents / max(count($validated['items']), 1), 2)
            : 0.0;

        return [
            'status' => $order->status()->value,
            'payload' => $shipment->toPayload(),
            'totals' => [
                'cents' => (int) round($order->total_cents * 100),
                'average_item_price' => $ratio,
            ],
            'line_item_count' => count($lineItems),
            'carrier' => $shipmentModel->carrier->value,
        ];
    }

    /**
     * @param  list<array{sku: string, quantity: int, price: int|float|string}>  $items
     */
    protected function calculateTotal(array $items): int
    {
        $sum = 0;

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $price = is_string($item['price']) ? (float) $item['price'] : $item['price'];
            $sum += (int) round($quantity * ((float) $price * 100));
        }

        return $sum;
    }
}
