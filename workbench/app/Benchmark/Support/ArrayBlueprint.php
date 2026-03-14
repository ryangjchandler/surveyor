<?php

namespace App\Benchmark\Support;

class ArrayBlueprint
{
    /**
     * @return array{currency: string, locale: string, tax_rate: float, tags: list<string>}
     */
    public static function defaults(): array
    {
        /** @var array{currency: string, locale: string, tax_rate: float, tags: list<string>} $data */
        $data = include __DIR__.'/bootstrap_benchmark.php';

        return $data;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function merge(array $overrides): array
    {
        $defaults = self::defaults();

        return [
            ...$defaults,
            ...$overrides,
            'tags' => [...$defaults['tags'], ...($overrides['tags'] ?? [])],
        ];
    }
}
