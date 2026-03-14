<?php

namespace App\Benchmark\Models;

use App\Benchmark\Casts\MinorUnitsCast;
use App\Benchmark\Casts\OrderMetaCast;
use App\Benchmark\Enums\Channel;
use App\Benchmark\Enums\OrderStatus;
use App\Benchmark\Traits\HasStateTransitions;
use App\Benchmark\Traits\InteractsWithMeta;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $reference
 * @property float $total_cents
 * @property array<string, mixed>|null $meta_data
 *
 * @method static create(array<string, mixed> $attributes = [])
 */
class Order extends Model
{
    use HasStateTransitions;
    use InteractsWithMeta;

    protected $table = 'benchmark_orders';

    protected $fillable = [
        'reference',
        'status',
        'channel',
        'meta_data',
        'total_cents',
    ];

    protected function casts(): array
    {
        return [
            'meta_data' => OrderMetaCast::class,
            'total_cents' => MinorUnitsCast::class,
            'status' => OrderStatus::class,
            'channel' => Channel::class,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    /**
     * @return Attribute<float, never>
     */
    protected function total(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) ($this->total_cents ?? 0.0),
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->reference.' ('.$this->status->value.')',
        );
    }
}
