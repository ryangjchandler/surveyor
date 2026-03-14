<?php

namespace App\Benchmark\Http\Controllers;

use App\Benchmark\Http\Requests\StoreOrderRequest;
use App\Benchmark\Services\OrderPipeline;
use App\Benchmark\Services\ReportBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, OrderPipeline $pipeline): JsonResponse
    {
        $result = $pipeline->build($request->validated());

        return response()->json([
            'ok' => true,
            'data' => $result,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnostics(Request $request, ReportBuilder $builder): array
    {
        $numbers = [1, 2, 3, 4, 5];

        $mapped = array_map(fn (int $item): int => $item * 2, $numbers);
        $active = array_filter($mapped, fn (int $item): bool => $item % 3 !== 0);

        $totals = [
            'sum' => array_sum($active),
            'count' => count($active),
        ];

        $flag = $request->boolean('include_report');

        return [
            'request_id' => $request->header('X-Request-Id') ?? 'n/a',
            'totals' => $totals,
            'report' => $flag ? $builder->summarize([]) : null,
        ];
    }
}
