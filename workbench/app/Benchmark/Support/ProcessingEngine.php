<?php

namespace App\Benchmark\Support;

use App\Benchmark\DTO\ShipmentData;

class ProcessingEngine
{
    /**
     * @template T of object
     *
     * @param  list<T>  $items
     * @param  callable(T): array<string, mixed>|null  $transform
     * @return array{count: int, first: array<string, mixed>|null, payloads: list<array<string, mixed>>}
     */
    public function process(array $items, ?callable $transform = null): array
    {
        $payloads = [];

        foreach ($items as $item) {
            if (! method_exists($item, 'toPayload')) {
                continue;
            }

            /** @var array<string, mixed> $base */
            $base = $item->toPayload();
            $payloads[] = $transform ? ($transform)($item) : $base;
        }

        $first = $payloads[0] ?? null;

        return [
            'count' => count($payloads),
            'first' => $first,
            'payloads' => $payloads,
        ];
    }

    /**
     * @param  ShipmentData|array<string, mixed>  $data
     */
    public function normalize(ShipmentData|array $data): array
    {
        if ($data instanceof ShipmentData) {
            return $data->toPayload();
        }

        if (! isset($data['address']) || ! is_array($data['address'])) {
            $data['address'] = [];
        }

        $data['reference'] = (string) ($data['reference'] ?? 'unknown');

        return $data;
    }
}
