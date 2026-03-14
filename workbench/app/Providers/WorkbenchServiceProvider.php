<?php

namespace App\Providers;

use App\Benchmark\Services\BenchmarkSuite;
use App\Benchmark\Support\ArrayBlueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(BenchmarkSuite::class, fn () => new BenchmarkSuite);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $config = ArrayBlueprint::defaults();

        if (($config['locale'] ?? null) === 'en_US') {
            Route::pattern('benchmark_ref', '[A-Z0-9\-]+');
        }
    }
}
