# Benchmarks

This directory contains a lightweight benchmark suite for PHPPeg. It measures parser time and memory on deterministic, generated inputs so you can compare performance before and after parser changes without introducing large static fixtures.

## Running Benchmarks

The main entrypoint is the Laravel Zero binary:

```bash
php bin/phpeg benchmark
```

If the binary is installed or made executable, you can also run:

```bash
./bin/phpeg benchmark
```

Run it through Composer:

```bash
composer benchmark
```

Supported options:

```bash
php bin/phpeg benchmark --iterations=5
php bin/phpeg benchmark --scale=small
php bin/phpeg benchmark --scale=medium
php bin/phpeg benchmark --scale=large
php bin/phpeg benchmark --filter=arithmetic
php bin/phpeg benchmark --mode=default --mode=speed
php bin/phpeg benchmark --json
```

`--scale` controls generated input size:

- `small`: quick local smoke run
- `medium`: default baseline run
- `large`: heavier stress run

`--iterations` repeats each benchmark and reports total, average, minimum, and maximum parse time. Memory metrics are reported as average memory before parsing, average memory after parsing, average delta, and maximum observed peak memory.

`--filter` matches either the benchmark name or slug and lets you focus on a single case such as `arithmetic`, `json`, `recursion`, or `backtracking`.

`--mode` selects one or more explicit flag combinations. When omitted, the suite runs:

- `default`: memoization with lazy node text and full error tracking
- `speed`: default plus lighter successful-parse error tracking and empty-match reuse
- `memory`: memoization disabled, with lazy node text still enabled

`--json` prints machine-readable output to stdout for CI or scripting. Historical files are still written to disk.

## Benchmark Cases

The suite currently includes eight benchmark cases:

- `Large arithmetic expression`: long precedence-sensitive expressions with nested parentheses, integers, and decimals.
- `Deep nested recursion`: repeated `f(f(...value...))` style nesting to stress recursive descent depth and stack behavior.
- `Large JSON-like document`: realistic nested objects and arrays with strings, numbers, booleans, and null values.
- `Backtracking-heavy grammar`: many long alternatives sharing the same prefix to stress ordered-choice backtracking.
- `Named capture and span checks`: a compact invented markup format that exercises named captures on tag names and span equality on fixed-width event codes.
- `Island parsing with lakes`: a recursive island grammar over a mixed config-like document, using native lake nodes for water capture.
- `Island parsing with manual water`: the same mixed document shape implemented with an explicit manual water rule for comparison.
- `Island parsing with annotated water`: the same mixed document shape with `@water` marking reusable water rules.

Each benchmark defines its own grammar, generates input at runtime, and validates that parsing actually succeeds on the generated input.

Benchmark case discovery is configured in [app/config/benchmarks.php](/Users/manu/Projects/EmanueleCoppola/phpeg/app/config/benchmarks.php). Benchmark implementation classes live under [app/Benchmarks](/Users/manu/Projects/EmanueleCoppola/phpeg/app/Benchmarks) and [app/Benchmarks/Cases](/Users/manu/Projects/EmanueleCoppola/phpeg/app/Benchmarks/Cases).

## Historical Results

Every run writes:

- a timestamped JSON snapshot under `benchmarks/results/`
- an append-only CSV file at `benchmarks/results/history.csv`

The JSON file stores full run metadata including timestamp, git commit and branch when detectable, dirty/clean status, PHP version, platform, scale, iterations, and each benchmark result. The CSV file appends one row per benchmark case per run for easy spreadsheet or shell-based comparison.

## Comparing Runs

Run the comparison command after at least two benchmark runs:

```bash
php bin/phpeg benchmark:compare
composer benchmark:compare
```

The comparison command reads the two most recent JSON result files and shows the previous and current average time and peak memory for each benchmark and parser mode, along with the percentage change.

When the repository's `Benchmark PR` workflow runs on a pull request, it benchmarks the base branch and the PR head back to back, then posts the comparison report as a sticky PR comment and as a job summary.

## Interpreting Regressions

Use `medium` runs as the default baseline unless you specifically need a quicker smoke check or a larger stress run.

Time regressions:

- higher average time means parsing got slower
- lower average time means parsing improved

Memory regressions:

- higher peak memory means the parser retained or allocated more memory during parsing
- higher memory delta means per-iteration memory usage grew more between benchmark start and finish

Treat small changes carefully. Local machine noise, different PHP builds, and unrelated background work can move timings slightly, so compare multiple runs when a regression is marginal.
