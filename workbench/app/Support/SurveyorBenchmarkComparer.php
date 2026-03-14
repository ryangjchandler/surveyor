<?php

namespace App\Support;

use InvalidArgumentException;

class SurveyorBenchmarkComparer
{
    /**
     * @return array<string, mixed>
     */
    public function compare(string $beforePath, string $afterPath): array
    {
        $beforePath = $this->resolvePath($beforePath);
        $afterPath = $this->resolvePath($afterPath);

        $before = $this->loadBenchmark($beforePath);
        $after = $this->loadBenchmark($afterPath);

        $beforeScenarios = $before['scenarios'];
        $afterScenarios = $after['scenarios'];

        $scenarioNames = array_unique(array_merge(array_keys($beforeScenarios), array_keys($afterScenarios)));
        sort($scenarioNames);

        $rows = [];

        foreach ($scenarioNames as $scenario) {
            $beforeScenario = $beforeScenarios[$scenario] ?? null;
            $afterScenario = $afterScenarios[$scenario] ?? null;

            $beforeTimeMedian = $beforeScenario['time_ms']['median'] ?? null;
            $afterTimeMedian = $afterScenario['time_ms']['median'] ?? null;

            $beforeTimeP95 = $beforeScenario['time_ms']['p95'] ?? null;
            $afterTimeP95 = $afterScenario['time_ms']['p95'] ?? null;

            $beforePeakMedian = $beforeScenario['peak_memory_mb']['median'] ?? null;
            $afterPeakMedian = $afterScenario['peak_memory_mb']['median'] ?? null;

            $rows[] = [
                'scenario' => $scenario,
                'before_time_median_ms' => $beforeTimeMedian,
                'after_time_median_ms' => $afterTimeMedian,
                'time_median_delta_pct' => $this->percentageDelta($afterTimeMedian, $beforeTimeMedian),
                'before_time_p95_ms' => $beforeTimeP95,
                'after_time_p95_ms' => $afterTimeP95,
                'time_p95_delta_pct' => $this->percentageDelta($afterTimeP95, $beforeTimeP95),
                'before_peak_median_mb' => $beforePeakMedian,
                'after_peak_median_mb' => $afterPeakMedian,
                'peak_median_delta_pct' => $this->percentageDelta($afterPeakMedian, $beforePeakMedian),
            ];
        }

        $timeDeltas = array_values(array_filter(array_map(
            fn ($row) => $row['time_median_delta_pct'],
            $rows,
        ), fn ($item) => is_numeric($item)));

        $peakDeltas = array_values(array_filter(array_map(
            fn ($row) => $row['peak_median_delta_pct'],
            $rows,
        ), fn ($item) => is_numeric($item)));

        return [
            'before_path' => $beforePath,
            'after_path' => $afterPath,
            'before_meta' => $before['meta'],
            'after_meta' => $after['meta'],
            'rows' => $rows,
            'summary' => [
                'scenario_count' => count($rows),
                'avg_time_median_delta_pct' => $this->average($timeDeltas),
                'avg_peak_median_delta_pct' => $this->average($peakDeltas),
                'best_time_change' => $this->bestChange($rows, 'time_median_delta_pct', 'min'),
                'worst_time_change' => $this->bestChange($rows, 'time_median_delta_pct', 'max'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadBenchmark(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('Benchmark file does not exist: '.$path);
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || $contents === '') {
            throw new InvalidArgumentException('Benchmark file is empty: '.$path);
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded) || ! isset($decoded['meta']) || ! isset($decoded['scenarios']) || ! is_array($decoded['scenarios'])) {
            throw new InvalidArgumentException('Benchmark file is not a valid benchmark JSON: '.$path);
        }

        return $decoded;
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
     * @param  list<float|int>  $values
     */
    protected function average(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    protected function bestChange(array $rows, string $field, string $direction): ?array
    {
        $comparableRows = array_values(array_filter($rows, fn ($row) => is_numeric($row[$field] ?? null)));

        if ($comparableRows === []) {
            return null;
        }

        usort($comparableRows, function ($a, $b) use ($field, $direction) {
            if ($direction === 'min') {
                return $a[$field] <=> $b[$field];
            }

            return $b[$field] <=> $a[$field];
        });

        return $comparableRows[0];
    }
}
