<?php

namespace App\Benchmark\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

class MacroRegistry
{
    public static function register(): void
    {
        Str::macro('squishAndLower', function (string $value): string {
            return Str::of($value)->squish()->lower()->toString();
        });

        Stringable::macro('surveyorTag', function (): string {
            return '['.$this->upper().']';
        });
    }
}
