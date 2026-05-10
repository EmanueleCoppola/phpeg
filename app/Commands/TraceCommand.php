<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\App\Commands;

use EmanueleCoppola\PHPeg\App\Trace\ParserTraceExporter;
use EmanueleCoppola\PHPeg\App\Trace\ParserTraceRecorder;
use EmanueleCoppola\PHPeg\Error\GrammarSyntaxError;
use EmanueleCoppola\PHPeg\Error\ParseError;
use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Loader\CleanPeg\CleanPegGrammarLoader;
use EmanueleCoppola\PHPeg\Loader\Peg\PegGrammarLoader;
use LaravelZero\Framework\Commands\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Parses an input file and exports a trace JSON document for the CLI step viewer.
 */
class TraceCommand extends Command
{
    /**
     * The command signature.
     *
     * @var string
     */
    protected $signature = 'trace
                            {--grammar= : Path to the grammar file}
                            {--i|input= : Path to the source file to parse}
                            {--o|output= : Write the trace JSON payload to a file}
                            {--grammar-format=auto : Grammar format: auto, peg, or cleanpeg}
                            {--start-rule= : Override the grammar start rule}';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Export a parser trace JSON document for interactive step debugging.';

    /**
     * Executes the trace command.
     */
    public function handle(): int
    {
        try {
            $grammarPath = $this->normalizeRequiredPath($this->option('grammar'), 'grammar');
            $inputPath = $this->normalizeRequiredPath($this->option('input'), 'input');
            $outputPath = $this->normalizeOptionalString($this->option('output'));
            $grammarFormat = $this->normalizeGrammarFormat((string) $this->option('grammar-format'));
            $startRule = $this->normalizeOptionalString($this->option('start-rule'));

            $grammar = $this->loadGrammar($grammarPath, $grammarFormat, $startRule);
            $grammarSource = $this->loadFile($grammarPath);
            $input = $this->loadFile($inputPath);
            $recorder = new ParserTraceRecorder();
            $result = $grammar->parse($input, $startRule, null, $recorder);
            $document = (new ParserTraceExporter())->export(
                grammar: $grammar,
                grammarPath: $grammarPath,
                grammarFormat: $grammarFormat,
                grammarSource: $grammarSource,
                inputPath: $inputPath,
                inputSource: $input,
                steps: $recorder->steps(),
                result: $result,
            );

            $this->writeJsonPayload($document, $outputPath);

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->renderCliError($throwable);

            return self::FAILURE;
        }
    }

    /**
     * Normalizes a required path-like option or argument.
     */
    private function normalizeRequiredPath(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Missing required %s path.', $name));
        }

        return $value;
    }

    /**
     * Loads a grammar from disk using the requested format.
     */
    private function loadGrammar(string $path, string $format, ?string $startRule): Grammar
    {
        return match ($format) {
            'peg' => (new PegGrammarLoader())->fromFile($path),
            'cleanpeg' => (new CleanPegGrammarLoader())->fromFile($path, $startRule),
            default => throw new InvalidArgumentException(sprintf('Unsupported grammar format "%s".', $format)),
        };
    }

    /**
     * Reads the full contents of a file.
     */
    private function loadFile(string $path): string
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('Unable to read file: %s', $path));
        }

        return $contents;
    }

    /**
     * Normalizes a possibly empty string option.
     */
    private function normalizeOptionalString(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Normalizes the grammar format option and infers auto-detection when needed.
     */
    private function normalizeGrammarFormat(string $format): string
    {
        $normalized = strtolower(trim($format));

        if ($normalized === 'auto') {
            return $this->inferGrammarFormat($this->normalizeRequiredPath($this->option('grammar'), 'grammar'));
        }

        return $normalized;
    }

    /**
     * Writes the JSON payload to stdout or to a file path.
     *
     * @param array<string, mixed> $payload
     */
    private function writeJsonPayload(array $payload, ?string $outputPath): void
    {
        $json = (new ParserTraceExporter())->encode($payload);

        if ($outputPath === null) {
            $this->output->write($json . PHP_EOL);

            return;
        }

        if (file_put_contents($outputPath, $json . PHP_EOL) === false) {
            throw new RuntimeException(sprintf('Unable to write trace output to %s.', $outputPath));
        }
    }

    /**
     * Infers the grammar format from the file extension.
     */
    private function inferGrammarFormat(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'peg' => 'peg',
            'cleanpeg' => 'cleanpeg',
            default => throw new InvalidArgumentException(sprintf(
                'Unable to infer grammar format from "%s". Pass --grammar-format=peg or --grammar-format=cleanpeg.',
                $path,
            )),
        };
    }

    /**
     * Renders a caught exception using the CLI error style.
     */
    private function renderCliError(Throwable $throwable): void
    {
        $lines = [
            'ERROR',
            $this->friendlyErrorMessage($throwable),
        ];

        $hint = $this->friendlyErrorHint($throwable);
        if ($hint !== null) {
            $lines[] = '';
            $lines[] = 'Hint: ' . $hint;
        }

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

    /**
     * Returns a human-readable message for the provided exception.
     */
    private function friendlyErrorMessage(Throwable $throwable): string
    {
        if ($throwable instanceof GrammarSyntaxError) {
            return $throwable->getMessage();
        }

        if ($throwable instanceof InvalidArgumentException) {
            return $throwable->getMessage();
        }

        if ($throwable instanceof ParseError) {
            return $throwable->message();
        }

        return 'Unexpected error: ' . $throwable->getMessage();
    }

    /**
     * Returns a human-readable hint for the provided exception.
     */
    private function friendlyErrorHint(Throwable $throwable): ?string
    {
        if ($throwable instanceof InvalidArgumentException) {
            return 'Check the grammar and input paths, then rerun with the right format.';
        }

        return null;
    }
}
