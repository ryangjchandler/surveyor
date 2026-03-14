<?php

namespace App\Benchmark\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Paid = 'paid';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Canceled = 'canceled';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Pending, self::Canceled], true),
            self::Pending => in_array($next, [self::Paid, self::Canceled], true),
            self::Paid => in_array($next, [self::Packed, self::Canceled], true),
            self::Packed => $next === self::Shipped,
            self::Shipped, self::Canceled => false,
        };
    }
}
