<?php

namespace App\Benchmark\Traits;

trait InteractsWithMeta
{
    /** @var array<string, scalar|array<array-key, mixed>|null> */
    protected array $meta = [];

    /**
     * @param  scalar|array<array-key, mixed>|null  $value
     */
    public function setMeta(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }

    /**
     * @return scalar|array<array-key, mixed>|null
     */
    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    /**
     * @return array<string, scalar|array<array-key, mixed>|null>
     */
    public function allMeta(): array
    {
        return $this->meta;
    }
}
