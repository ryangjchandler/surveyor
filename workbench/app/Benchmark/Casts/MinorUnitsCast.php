<?php

namespace App\Benchmark\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class MinorUnitsCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): float
    {
        return round(((int) ($value ?? 0)) / 100, 2);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        if (is_string($value)) {
            if (! is_numeric($value)) {
                throw new InvalidArgumentException("{$key} must be numeric.");
            }

            $value = (float) $value;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value * 100);
        }

        if (is_bool($value) || $value === null) {
            return 0;
        }

        throw new InvalidArgumentException("Unable to cast {$key} to minor units.");
    }
}
