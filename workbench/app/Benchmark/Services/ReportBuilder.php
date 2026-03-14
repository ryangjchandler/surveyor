<?php

namespace App\Benchmark\Services;

use App\Benchmark\Enums\OrderStatus;
use App\Benchmark\Models\Order;
use Illuminate\Support\Facades\View;

class ReportBuilder
{
    /**
     * @param  list<Order>  $orders
     * @return array{summary: array<string, int>, report: string}
     */
    public function summarize(array $orders): array
    {
        $summary = [
            'draft' => 0,
            'pending' => 0,
            'paid' => 0,
            'packed' => 0,
            'shipped' => 0,
            'canceled' => 0,
        ];

        foreach ($orders as $order) {
            $bucket = match ($order->status()) {
                OrderStatus::Draft => 'draft',
                OrderStatus::Pending => 'pending',
                OrderStatus::Paid => 'paid',
                OrderStatus::Packed => 'packed',
                OrderStatus::Shipped => 'shipped',
                OrderStatus::Canceled => 'canceled',
            };

            $summary[$bucket]++;
        }

        $report = View::make('benchmark.orders.summary', [
            'summary' => $summary,
            'generatedAt' => now()->toDateTimeString(),
        ])->render();

        return [
            'summary' => $summary,
            'report' => $report,
        ];
    }
}
