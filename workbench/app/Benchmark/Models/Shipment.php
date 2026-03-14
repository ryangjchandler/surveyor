<?php

namespace App\Benchmark\Models;

use App\Benchmark\Casts\OrderMetaCast;
use App\Benchmark\Enums\ShipmentCarrier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    protected $table = 'benchmark_shipments';

    protected $fillable = [
        'order_id',
        'carrier',
        'tracking_number',
        'meta_data',
    ];

    protected function casts(): array
    {
        return [
            'carrier' => ShipmentCarrier::class,
            'meta_data' => OrderMetaCast::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
