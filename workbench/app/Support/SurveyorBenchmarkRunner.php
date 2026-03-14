<?php

namespace App\Support;

use InvalidArgumentException;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;

class SurveyorBenchmarkRunner
{
    /**
     * @param  array<int, string>  $paths
     * @param  array<int, string>  $benchmarks
     * @return array<string, mixed>
     */
    public function run(
        array $paths,
        array $benchmarks,
        int $iterations,
        int $warmup,
        string $scenario,
        ?string $outputPath = null,
        ?string $baselinePath = null,
    ): array {
        if ($iterations < 1) {
            throw new InvalidArgumentException('Iterations must be at least 1.');
        }

        if ($warmup < 0) {
            throw new InvalidArgumentException('Warmup iterations cannot be negative.');
        }

        $selectedScenarios = $this->resolveScenarios($scenario);
        $selectedBenchmarks = $this->resolveBenchmarks($benchmarks);

        $benchmarkPaths = $this->benchmarkPaths($selectedBenchmarks);
        $resolvedInputPaths = array_values(array_unique([...$benchmarkPaths, ...$paths]));

        $files = $this->collectPhpFiles($resolvedInputPaths);

        if ($files === []) {
            throw new InvalidArgumentException('No PHP files found for benchmark paths.');
        }

        $results = [];

        foreach ($selectedScenarios as $scenarioName) {
            $results[$scenarioName] = $this->runScenario($scenarioName, $files, $iterations, $warmup);
        }

        $resolvedBaselinePath = $baselinePath ? $this->resolvePath($baselinePath) : null;
        $baseline = $this->loadBaseline($resolvedBaselinePath, $selectedScenarios);

        $outputPath ??= $this->defaultOutputPath();
        $this->ensureDirectory(dirname($outputPath));

        $comparison = $this->buildComparison($results, $baseline);

        $payload = [
            'meta' => [
                'timestamp' => date(DATE_ATOM),
                'php_version' => PHP_VERSION,
                'iterations' => $iterations,
                'warmup' => $warmup,
                'scenarios' => $selectedScenarios,
                'benchmarks' => $selectedBenchmarks,
                'paths' => $resolvedInputPaths,
                'resolved_paths' => $files,
                'file_count' => count($files),
                'baseline_path' => $resolvedBaselinePath,
                'output_path' => $outputPath,
            ],
            'scenarios' => $results,
            'comparison' => $comparison,
        ];

        file_put_contents($outputPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $payload;
    }

    /**
     * @param  array<string, array<string, mixed>>  $current
     * @param  array<string, array<string, mixed>>|null  $baseline
     * @return array<string, array<string, float|null>>
     */
    protected function buildComparison(array $current, ?array $baseline): array
    {
        if ($baseline === null) {
            return [];
        }

        $comparison = [];

        foreach ($current as $scenario => $data) {
            $baselineScenario = $baseline[$scenario] ?? null;

            $currentTimeMedian = $data['time_ms']['median'] ?? null;
            $baselineTimeMedian = $baselineScenario['time_ms']['median'] ?? null;

            $currentPeakMedian = $data['peak_memory_mb']['median'] ?? null;
            $baselinePeakMedian = $baselineScenario['peak_memory_mb']['median'] ?? null;

            $comparison[$scenario] = [
                'time_ms_median_pct' => $this->percentageDelta($currentTimeMedian, $baselineTimeMedian),
                'peak_memory_mb_median_pct' => $this->percentageDelta($currentPeakMedian, $baselinePeakMedian),
            ];
        }

        return $comparison;
    }

    protected function percentageDelta(mixed $current, mixed $baseline): ?float
    {
        if (! is_numeric($current) || ! is_numeric($baseline)) {
            return null;
        }

        $baseline = (float) $baseline;

        if ($baseline == 0.0) {
            return null;
        }

        return round((((float) $current - $baseline) / $baseline) * 100, 2);
    }

    /**
     * @param  list<string>  $selectedScenarios
     * @return array<string, array<string, mixed>>|null
     */
    protected function loadBaseline(?string $baselinePath, array $selectedScenarios): ?array
    {
        if ($baselinePath === null) {
            return null;
        }

        if (! is_file($baselinePath)) {
            throw new InvalidArgumentException('Baseline file does not exist: '.$baselinePath);
        }

        $contents = file_get_contents($baselinePath);

        if (! is_string($contents) || $contents === '') {
            throw new InvalidArgumentException('Baseline file is empty: '.$baselinePath);
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded) || ! isset($decoded['scenarios']) || ! is_array($decoded['scenarios'])) {
            throw new InvalidArgumentException('Baseline file is not a valid benchmark JSON: '.$baselinePath);
        }

        $scenarios = [];

        foreach ($selectedScenarios as $scenario) {
            if (isset($decoded['scenarios'][$scenario]) && is_array($decoded['scenarios'][$scenario])) {
                $scenarios[$scenario] = $decoded['scenarios'][$scenario];
            }
        }

        return $scenarios;
    }

    /**
     * @return list<string>
     */
    protected function resolveScenarios(string $scenario): array
    {
        $scenario = strtolower(trim($scenario));

        if ($scenario === 'all') {
            return ['cold', 'warm-memory', 'warm-disk'];
        }

        $allowed = ['cold', 'warm-memory', 'warm-disk'];

        if (! in_array($scenario, $allowed, true)) {
            throw new InvalidArgumentException('Invalid scenario. Allowed: cold, warm-memory, warm-disk, all.');
        }

        return [$scenario];
    }

    /**
     * @param  array<int, string>  $requestedBenchmarks
     * @return list<string>
     */
    protected function resolveBenchmarks(array $requestedBenchmarks): array
    {
        $available = array_keys($this->registeredBenchmarks());

        if ($requestedBenchmarks === []) {
            return $available;
        }

        $requested = array_values(array_unique(array_filter(array_map(
            fn ($benchmark) => strtolower(trim((string) $benchmark)),
            $requestedBenchmarks,
        ))));

        $invalid = array_values(array_diff($requested, $available));

        if ($invalid !== []) {
            throw new InvalidArgumentException(
                'Invalid benchmark name(s): '.implode(', ', $invalid).'. Available: '.implode(', ', $available)
            );
        }

        return $requested;
    }

    /**
     * @param  list<string>  $benchmarks
     * @return list<string>
     */
    protected function benchmarkPaths(array $benchmarks): array
    {
        $registered = $this->registeredBenchmarks();
        $paths = [];

        foreach ($benchmarks as $benchmark) {
            foreach ($registered[$benchmark]['paths'] as $path) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return array<string, array{description: string, paths: list<string>}>
     */
    protected function registeredBenchmarks(): array
    {
        return [
            'analysis-models' => [
                'description' => 'Eloquent models, casts, relations, and accessors',
                'paths' => [
                    $this->packageRootPath('workbench/app/Benchmark/Models'),
                ],
            ],
            'analysis-http' => [
                'description' => 'Controllers and FormRequest validation analysis',
                'paths' => [
                    $this->packageRootPath('workbench/app/Benchmark/Http'),
                ],
            ],
            'analysis-services' => [
                'description' => 'Service-layer flow control, unions, arrays, and facades',
                'paths' => [
                    $this->packageRootPath('workbench/app/Benchmark/Services'),
                ],
            ],
            'analysis-language' => [
                'description' => 'Enums, traits, DTOs, and support utilities',
                'paths' => [
                    $this->packageRootPath('workbench/app/Benchmark/Enums'),
                    $this->packageRootPath('workbench/app/Benchmark/Traits'),
                    $this->packageRootPath('workbench/app/Benchmark/DTO'),
                    $this->packageRootPath('workbench/app/Benchmark/Casts'),
                    $this->packageRootPath('workbench/app/Benchmark/Support'),
                    $this->packageRootPath('workbench/app/Providers/WorkbenchServiceProvider.php'),
                ],
            ],
        ];
    }

    /**
     * @param  list<string>  $files
     * @return array<string, mixed>
     */
    protected function runScenario(string $scenario, array $files, int $iterations, int $warmup): array
    {
        $diskCacheDir = null;

        AnalyzedCache::clear();

        if ($scenario === 'warm-disk') {
            $diskCacheDir = $this->createDiskCacheDirectory();
            AnalyzedCache::enableDiskCache($diskCacheDir);
        } else {
            AnalyzedCache::disable();
        }

        try {
            for ($i = 0; $i < $warmup; $i++) {
                $this->executeIteration($files, $scenario);
            }

            $measurements = [];

            for ($i = 0; $i < $iterations; $i++) {
                $measurements[] = $this->executeIteration($files, $scenario);
            }
        } finally {
            AnalyzedCache::clear();
            AnalyzedCache::disable();

            if ($diskCacheDir !== null) {
                $this->deleteDirectory($diskCacheDir);
            }
        }

        $timeValues = array_column($measurements, 'time_ms');
        $peakValues = array_column($measurements, 'peak_memory_mb');
        $deltaValues = array_column($measurements, 'memory_delta_mb');

        return [
            'iterations' => $iterations,
            'warmup' => $warmup,
            'time_ms' => $this->summarize($timeValues),
            'peak_memory_mb' => $this->summarize($peakValues),
            'memory_delta_mb' => $this->summarize($deltaValues),
            'measurements' => $measurements,
        ];
    }

    /**
     * @param  list<string>  $files
     * @return array<string, float>
     */
    protected function executeIteration(array $files, string $scenario): array
    {
        if ($scenario === 'cold') {
            AnalyzedCache::clear();
        }

        if ($scenario === 'warm-disk') {
            AnalyzedCache::clearMemory();
        }

        gc_collect_cycles();
        memory_reset_peak_usage();

        $startMemory = memory_get_usage(true);
        $start = hrtime(true);

        $analyzer = app(Analyzer::class);

        foreach ($files as $path) {
            $analyzer->analyze($path);
        }

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        return [
            'time_ms' => round($elapsedMs, 3),
            'memory_delta_mb' => round(($endMemory - $startMemory) / 1024 / 1024, 3),
            'peak_memory_mb' => round($peakMemory / 1024 / 1024, 3),
        ];
    }

    /**
     * @param  list<float>  $values
     * @return array{min: float, median: float, mean: float, p95: float, max: float}
     */
    protected function summarize(array $values): array
    {
        sort($values);

        $count = count($values);
        $middle = intdiv($count, 2);

        $median = $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];

        $p95Index = max(0, (int) ceil($count * 0.95) - 1);

        return [
            'min' => round($values[0], 3),
            'median' => round($median, 3),
            'mean' => round(array_sum($values) / $count, 3),
            'p95' => round($values[$p95Index], 3),
            'max' => round($values[$count - 1], 3),
        ];
    }

    protected function defaultOutputPath(): string
    {
        return $this->packageRootPath('workbench/storage/benchmarks/surveyor-benchmark-'.date('Ymd-His').'.json');
    }

    /**
     * @param  array<int, string>  $paths
     * @return list<string>
     */
    protected function collectPhpFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            $resolvedPath = $this->resolvePath($path);

            if (is_file($resolvedPath) && str_ends_with($resolvedPath, '.php')) {
                $files[$resolvedPath] = true;

                continue;
            }

            if (! is_dir($resolvedPath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($resolvedPath, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $files[$file->getPathname()] = true;
            }
        }

        ksort($files);

        return array_keys($files);
    }

    protected function resolvePath(string $path): string
    {
        if ($path === '' || $path === '.') {
            return base_path();
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }

        $basePathCandidate = base_path($path);

        if (file_exists($basePathCandidate)) {
            return $basePathCandidate;
        }

        return $this->packageRootPath($path);
    }

    protected function packageRootPath(string $path = ''): string
    {
        $root = dirname(__DIR__, 3);

        if ($path === '') {
            return $root;
        }

        return $root.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
    }

    protected function createDiskCacheDirectory(): string
    {
        $dir = $this->packageRootPath('workbench/storage/benchmarks/cache-'.bin2hex(random_bytes(6)));
        $this->ensureDirectory($dir);

        return $dir;
    }

    protected function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    protected function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());

                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
