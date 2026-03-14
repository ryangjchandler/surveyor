<?php

namespace App\Benchmark\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class OrderMetaCast implements CastsAttributes
{
    /**
     * @return array<string, scalar|array<array-key, mixed>|null>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        $meta = [];

        foreach ($decoded as $metaKey => $metaValue) {
            if (! is_string($metaKey)) {
                continue;
            }

            if (is_scalar($metaValue) || is_array($metaValue) || $metaValue === null) {
                $meta[$metaKey] = $metaValue;
            }
        }

        return $meta;
    }

    /**
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_string($value)) {
            return [$key => $value];
        }

        if (! is_array($value)) {
            return [$key => '{}'];
        }

        return [$key => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'];
    }
}
