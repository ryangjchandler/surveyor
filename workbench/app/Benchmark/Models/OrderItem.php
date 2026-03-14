<?php

namespace App\Benchmark\Models;

use App\Benchmark\Casts\MinorUnitsCast;
use App\Benchmark\Enums\LineItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'benchmark_order_items';

    protected $fillable = [
        'order_id',
        'sku',
        'quantity',
        'item_type',
        'price_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'item_type' => LineItemType::class,
            'price_cents' => MinorUnitsCast::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
