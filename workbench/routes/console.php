<?php

use App\Support\SurveyorBenchmarkComparer;
use App\Support\SurveyorBenchmarkRunner;
use Illuminate\Support\Facades\Artisan;

Artisan::command('surveyor:benchmark
    {--benchmark=* : Named benchmark suites to run}
    {--path=* : Files or directories to analyze (relative to base path by default)}
    {--iterations=10 : Number of measured iterations per scenario}
    {--warmup=2 : Number of warmup iterations per scenario}
    {--scenario=all : Scenario to run (cold, warm-memory, warm-disk, all)}
    {--baseline= : Optional baseline JSON file for delta comparison}
    {--output= : Optional JSON output path}', function (SurveyorBenchmarkRunner $runner) {
    $result = $runner->run(
        paths: (array) $this->option('path'),
        benchmarks: (array) $this->option('benchmark'),
        iterations: (int) $this->option('iterations'),
        warmup: (int) $this->option('warmup'),
        scenario: (string) $this->option('scenario'),
        outputPath: $this->option('output') ?: null,
        baselinePath: $this->option('baseline') ?: null,
    );

    $this->info('Surveyor benchmark complete.');

    $rows = [];

    foreach ($result['scenarios'] as $scenarioName => $scenarioData) {
        $deltaTime = $result['comparison'][$scenarioName]['time_ms_median_pct'] ?? null;
        $deltaPeak = $result['comparison'][$scenarioName]['peak_memory_mb_median_pct'] ?? null;

        $rows[] = [
            $scenarioName,
            number_format($scenarioData['time_ms']['median'], 2),
            number_format($scenarioData['time_ms']['mean'], 2),
            number_format($scenarioData['time_ms']['p95'], 2),
            number_format($scenarioData['peak_memory_mb']['median'], 2),
            number_format($scenarioData['peak_memory_mb']['max'], 2),
            is_numeric($deltaTime) ? sprintf('%+.2f%%', $deltaTime) : 'n/a',
            is_numeric($deltaPeak) ? sprintf('%+.2f%%', $deltaPeak) : 'n/a',
        ];
    }

    $this->table(
        ['Scenario', 'Median (ms)', 'Mean (ms)', 'P95 (ms)', 'Peak Median (MB)', 'Peak Max (MB)', 'Delta Time', 'Delta Peak'],
        $rows,
    );

    $this->line('Benchmark suites: '.implode(', ', $result['meta']['benchmarks']));
    $this->line('Files analyzed: '.$result['meta']['file_count']);
    if ($result['meta']['baseline_path']) {
        $this->line('Baseline: '.$result['meta']['baseline_path']);
    }
    $this->line('Output: '.$result['meta']['output_path']);
})->purpose('Benchmark Surveyor runtime and memory usage');

Artisan::command('benchmark:compare
    {before : Baseline benchmark JSON file}
    {after : New benchmark JSON file}', function (SurveyorBenchmarkComparer $comparer) {
    $result = $comparer->compare(
        beforePath: (string) $this->argument('before'),
        afterPath: (string) $this->argument('after'),
    );

    $this->info('Surveyor benchmark comparison');

    $relativePath = function (string $path): string {
        $cwd = getcwd() ?: '';

        if ($cwd !== '' && str_starts_with($path, $cwd.DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($cwd) + 1);
        }

        return $path;
    };

    $truncate = function (string $value, int $length = 72): string {
        if (strlen($value) <= $length) {
            return $value;
        }

        return '...'.substr($value, -($length - 3));
    };

    $formatNumber = fn ($value): string => is_numeric($value) ? number_format((float) $value, 2) : 'n/a';
    $formatDelta = fn ($value): string => is_numeric($value) ? sprintf('%+.2f%%', (float) $value) : 'n/a';

    $this->line('Before: '.$truncate($relativePath($result['before_path'])));
    $this->line('After:  '.$truncate($relativePath($result['after_path'])));
    $this->line('When:   '.($result['before_meta']['timestamp'] ?? 'n/a').' -> '.($result['after_meta']['timestamp'] ?? 'n/a'));
    $this->line('PHP:    '.($result['before_meta']['php_version'] ?? 'n/a').' -> '.($result['after_meta']['php_version'] ?? 'n/a'));
    $this->line('Files:  '.($result['before_meta']['file_count'] ?? 'n/a').' -> '.($result['after_meta']['file_count'] ?? 'n/a'));
    $this->line('');

    $summaryRows = [];

    foreach ($result['rows'] as $row) {
        $summaryRows[] = [
            $row['scenario'],
            $formatDelta($row['time_median_delta_pct']),
            $formatDelta($row['time_p95_delta_pct']),
            $formatDelta($row['peak_median_delta_pct']),
        ];
    }

    $this->table(
        ['Scenario', 'Time d%', 'P95 d%', 'Peak d%'],
        $summaryRows,
    );

    foreach ($result['rows'] as $row) {
        $this->line('');
        $this->line('<options=bold>'.$row['scenario'].'</>');
        $this->table(
            ['Metric', 'Before', 'After', 'Delta'],
            [
                ['Time median (ms)', $formatNumber($row['before_time_median_ms']), $formatNumber($row['after_time_median_ms']), $formatDelta($row['time_median_delta_pct'])],
                ['Time p95 (ms)', $formatNumber($row['before_time_p95_ms']), $formatNumber($row['after_time_p95_ms']), $formatDelta($row['time_p95_delta_pct'])],
                ['Peak median (MB)', $formatNumber($row['before_peak_median_mb']), $formatNumber($row['after_peak_median_mb']), $formatDelta($row['peak_median_delta_pct'])],
            ],
        );
    }

    $avgTime = $result['summary']['avg_time_median_delta_pct'];
    $avgPeak = $result['summary']['avg_peak_median_delta_pct'];

    $this->line('');
    $this->line('Average median time delta: '.(is_numeric($avgTime) ? sprintf('%+.2f%%', $avgTime) : 'n/a'));
    $this->line('Average median peak delta: '.(is_numeric($avgPeak) ? sprintf('%+.2f%%', $avgPeak) : 'n/a'));

    if ($result['summary']['best_time_change']) {
        $best = $result['summary']['best_time_change'];
        $this->line('Best time change: '.$best['scenario'].' ('.sprintf('%+.2f%%', $best['time_median_delta_pct']).')');
    }

    if ($result['summary']['worst_time_change']) {
        $worst = $result['summary']['worst_time_change'];
        $this->line('Worst time change: '.$worst['scenario'].' ('.sprintf('%+.2f%%', $worst['time_median_delta_pct']).')');
    }
})->purpose('Compare two Surveyor benchmark JSON reports');

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');
