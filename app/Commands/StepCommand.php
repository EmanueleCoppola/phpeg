<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\App\Commands;

use EmanueleCoppola\PHPeg\App\Trace\TraceConsoleRenderer;
use InvalidArgumentException;
use JsonException;
use LaravelZero\Framework\Commands\Command;
use Throwable;

/**
 * Opens a parser trace JSON file in an interactive terminal viewer.
 */
class StepCommand extends Command
{
    /**
     * The command signature.
     *
     * @var string
     */
    protected $signature = 'step
                            {--trace= : Path to a trace JSON file}
                            {--step=0 : Initial step index}
                            {--grammar-view=source : Grammar view mode: source or tree}
                            {--mode=all : Step mode: all, matched, or failures}';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Interactively inspect a parser trace with next/prev navigation.';

    /**
     * Executes the step viewer command.
     */
    public function handle(): int
    {
        try {
            $tracePath = $this->normalizeRequiredPath($this->option('trace'), 'trace');
            $initialStep = max(0, (int) $this->option('step'));
            $grammarView = $this->normalizeGrammarView((string) $this->option('grammar-view'));
            $mode = $this->normalizeStepMode((string) $this->option('mode'));
            $document = $this->loadTraceDocument($tracePath);
            $steps = $document['steps'] ?? [];

            if ($steps === []) {
                throw new InvalidArgumentException('The trace document does not contain any steps.');
            }

            $visibleSteps = $this->visibleSteps($steps, $mode);
            if ($visibleSteps === []) {
                throw new InvalidArgumentException(sprintf('The trace document does not contain any steps for mode "%s".', $mode));
            }

            $stepIndex = $this->resolveVisibleStepIndex($visibleSteps, $initialStep);
            $renderer = new TraceConsoleRenderer();
            $interactive = $this->isInteractive();

            if (!$interactive) {
                $viewDocument = $this->withVisibleSteps($document, $visibleSteps);
                $this->output->write($renderer->render($viewDocument, $stepIndex, $this->useColor(), $grammarView, $mode));

                return self::SUCCESS;
            }

            $this->runInteractiveSession($renderer, $document, $stepIndex, $grammarView, $mode);

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->renderCliError($throwable);

            return self::FAILURE;
        }
    }

    /**
     * Runs the interactive navigation loop.
     *
     * @param array<string, mixed> $document
     */
    private function runInteractiveSession(TraceConsoleRenderer $renderer, array $document, int $initialStep, string $grammarView, string $mode): void
    {
        $allSteps = $document['steps'] ?? [];
        $stepIndex = $initialStep;
        while (true) {
            $visibleSteps = $this->visibleSteps($allSteps, $mode);
            if ($visibleSteps === []) {
                $this->clearScreen();
                $this->output->writeln('No steps available for the selected mode.');

                break;
            }

            $stepIndex = $this->clampVisibleStepIndex($visibleSteps, $stepIndex);
            $viewDocument = $this->withVisibleSteps($document, $visibleSteps);
            $this->clearScreen();
            $this->output->write($renderer->render($viewDocument, $stepIndex, $this->useColor(), $grammarView, $mode));
            $this->output->write('trace> ');

            $line = fgets(STDIN);
            if ($line === false) {
                break;
            }

            $command = strtolower(trim($line));
            if ($command === '' || $command === 'n' || $command === 'next') {
                $stepIndex = min($stepIndex + 1, count($visibleSteps) - 1);

                continue;
            }

            if ($command === 'p' || $command === 'prev' || $command === 'previous') {
                $stepIndex = max($stepIndex - 1, 0);

                continue;
            }

            if ($command === 'q' || $command === 'quit' || $command === 'exit') {
                break;
            }

            if ($command === 'a' || $command === 'all') {
                $mode = 'all';
                $stepIndex = $this->resolveVisibleStepIndex($this->visibleSteps($allSteps, $mode), (int) ($visibleSteps[$stepIndex]['__traceIndex'] ?? 0));

                continue;
            }

            if ($command === 'm' || $command === 'matched') {
                $mode = 'matched';
                $stepIndex = $this->resolveVisibleStepIndex($this->visibleSteps($allSteps, $mode), (int) ($visibleSteps[$stepIndex]['__traceIndex'] ?? 0));

                continue;
            }

            if ($command === 'f' || $command === 'failures') {
                $mode = 'failures';
                $stepIndex = $this->resolveVisibleStepIndex($this->visibleSteps($allSteps, $mode), (int) ($visibleSteps[$stepIndex]['__traceIndex'] ?? 0));

                continue;
            }

            if (preg_match('/^(g|goto)\s+(\d+)$/', $command, $matches) === 1) {
                $stepIndex = max(0, min((int) $matches[2], count($visibleSteps) - 1));

                continue;
            }

            if ($command === 'help' || $command === '?') {
                $this->output->writeln('Commands: n/next, p/prev, g/goto <n>, a/all, m/matched, f/failures, q/quit');
            }
        }
    }

    /**
     * Loads and decodes a trace document from disk.
     *
     * @return array<string, mixed>
     */
    private function loadTraceDocument(string $path): array
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('Unable to read trace file: %s', $path));
        }

        try {
            $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(sprintf('Invalid trace JSON: %s', $exception->getMessage()), previous: $exception);
        }

        if (!is_array($document)) {
            throw new InvalidArgumentException('Trace file must decode to a JSON object.');
        }

        return $document;
    }

    /**
     * Normalizes a required path-like option.
     */
    private function normalizeRequiredPath(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Missing required %s path.', $name));
        }

        return $value;
    }

    /**
     * Normalizes the grammar view option.
     */
    private function normalizeGrammarView(string $view): string
    {
        $normalized = strtolower(trim($view));

        if (!in_array($normalized, ['source', 'tree'], true)) {
            throw new InvalidArgumentException('Unsupported grammar view "' . $view . '". Use "source" or "tree".');
        }

        return $normalized;
    }

    /**
     * Normalizes the step mode option.
     */
    private function normalizeStepMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));

        if (!in_array($normalized, ['all', 'matched', 'failures'], true)) {
            throw new InvalidArgumentException('Unsupported step mode "' . $mode . '". Use "all", "matched", or "failures".');
        }

        return $normalized;
    }

    /**
     * Returns the steps visible in the selected mode, annotated with trace indexes.
     *
     * @param list<array<string, mixed>> $steps
     * @return list<array<string, mixed>>
     */
    private function visibleSteps(array $steps, string $mode): array
    {
        $visible = [];

        foreach ($steps as $index => $step) {
            if (!$this->isVisibleStep($step, $mode)) {
                continue;
            }

            if (!is_array($step)) {
                continue;
            }

            $step['__traceIndex'] = $index;
            $visible[] = $step;
        }

        return $visible;
    }

    /**
     * Returns whether a step is visible in the selected mode.
     *
     * @param array<string, mixed>|mixed $step
     */
    private function isVisibleStep(mixed $step, string $mode): bool
    {
        if (!is_array($step)) {
            return false;
        }

        if ($mode === 'all') {
            return true;
        }

        if (($step['phase'] ?? null) !== 'exit') {
            return false;
        }

        $success = !empty($step['success']);
        if ($mode === 'matched') {
            return $success;
        }

        return !$success;
    }

    /**
     * Resolves the best visible step index for a trace index.
     *
     * @param list<array<string, mixed>> $visibleSteps
     */
    private function resolveVisibleStepIndex(array $visibleSteps, int $traceIndex): int
    {
        if ($visibleSteps === []) {
            return 0;
        }

        foreach ($visibleSteps as $index => $step) {
            $visibleTraceIndex = (int) ($step['__traceIndex'] ?? 0);
            if ($visibleTraceIndex >= $traceIndex) {
                return $index;
            }
        }

        return count($visibleSteps) - 1;
    }

    /**
     * Clamps a visible index to the available range.
     *
     * @param list<array<string, mixed>> $visibleSteps
     */
    private function clampVisibleStepIndex(array $visibleSteps, int $stepIndex): int
    {
        return max(0, min($stepIndex, count($visibleSteps) - 1));
    }

    /**
     * Returns a copy of the document with the selected visible steps.
     *
     * @param array<string, mixed> $document
     * @param list<array<string, mixed>> $visibleSteps
     * @return array<string, mixed>
     */
    private function withVisibleSteps(array $document, array $visibleSteps): array
    {
        $document['steps'] = $visibleSteps;

        return $document;
    }

    /**
     * Returns whether the current session is attached to an interactive TTY.
     */
    private function isInteractive(): bool
    {
        return function_exists('posix_isatty') ? @posix_isatty(STDIN) === true : true;
    }

    /**
     * Returns whether ANSI colors should be used.
     */
    private function useColor(): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        return function_exists('posix_isatty') ? @posix_isatty(STDOUT) === true : true;
    }

    /**
     * Clears the terminal screen before redrawing the trace view.
     */
    private function clearScreen(): void
    {
        if (!$this->useColor()) {
            $this->output->write(str_repeat(PHP_EOL, 2));

            return;
        }

        $this->output->write("\033[2J\033[H");
    }

    /**
     * Renders a caught exception using the CLI error style.
     */
    private function renderCliError(Throwable $throwable): void
    {
            $lines = [
                'ERROR',
                $throwable->getMessage(),
            ];

        $this->renderErrorBox($lines);
    }

    /**
     * Renders a boxed error message to the CLI.
     *
     * @param list<string> $lines
     */
    private function renderErrorBox(array $lines): void
    {
        $width = 0;

        foreach ($lines as $line) {
            $width = max($width, strlen($line));
        }

        $border = '+' . str_repeat('-', $width + 2) . '+';
        $this->line($border);

        foreach ($lines as $line) {
            $this->line(sprintf('| %-'. $width . 's |', $line));
        }

        $this->line($border);
    }
}
