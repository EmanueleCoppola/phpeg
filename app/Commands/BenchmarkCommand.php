<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\App\Commands;

use EmanueleCoppola\PHPeg\App\Benchmarks\BenchmarkApplication;
use EmanueleCoppola\PHPeg\App\Benchmarks\BenchmarkConsoleRenderer;
use EmanueleCoppola\PHPeg\App\Benchmarks\BenchmarkOptions;
use LaravelZero\Framework\Commands\Command;

/**
 * Runs the PHPeg benchmark suite through Laravel Zero.
 */
class BenchmarkCommand extends Command
{
    /**
     * The command signature.
     *
     * @var string
     */
    protected $signature = 'benchmark
                            {--iterations=3 : Number of iterations to run per benchmark}
                            {--scale=medium : Benchmark scale: small, medium, or large}
                            {--filter= : Filter benchmark name or slug}
                            {--mode=* : Benchmark mode slug(s): default, speed, memory}
                            {--json : Print machine-readable JSON output}';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Run parser performance benchmarks.';

    /**
     * Executes the benchmark suite.
     */
    public function handle(): int
    {
        $options = new BenchmarkOptions(
            iterations: (int) $this->option('iterations'),
            scale: (string) $this->option('scale'),
            filter: $this->option('filter') !== null && $this->option('filter') !== '' ? (string) $this->option('filter') : null,
            json: (bool) $this->option('json'),
            modes: array_values(array_filter(
                is_array($this->option('mode')) ? $this->option('mode') : [],
                static fn (mixed $mode): bool => is_string($mode) && $mode !== '',
            )),
        );

        $application = new BenchmarkApplication(
            $this->laravel->basePath(),
            (string) config('benchmarks.results_directory'),
            $this->benchmarkCaseClasses(),
        );
        $report = $application->run($options);
        $renderer = new BenchmarkConsoleRenderer();

        $this->output->write($options->json() ? $renderer->renderJson($report) . PHP_EOL : $renderer->renderHuman($report) . PHP_EOL);

        return $report->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Returns the configured benchmark case class list.
     *
     * @return list<class-string<\EmanueleCoppola\PHPeg\App\Benchmarks\BenchmarkCaseInterface>>
     */
    private function benchmarkCaseClasses(): array
    {
        $configured = config('benchmarks.cases', []);

        return is_array($configured) ? array_values($configured) : [];
    }
}
