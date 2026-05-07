<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\App\Commands;

use EmanueleCoppola\PHPeg\App\Support\AstDotExporter;
use EmanueleCoppola\PHPeg\App\Support\AstJsonExporter;
use EmanueleCoppola\PHPeg\Document\ParsedDocument;
use EmanueleCoppola\PHPeg\Error\GrammarSyntaxError;
use EmanueleCoppola\PHPeg\Error\ParseError;
use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Loader\CleanPeg\CleanPegGrammarLoader;
use EmanueleCoppola\PHPeg\Loader\Peg\PegGrammarLoader;
use LaravelZero\Framework\Commands\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Parses a source file with a file-based grammar and exports JSON output, with optional DOT parse tree export.
 */
class ParseCommand extends Command
{
    /**
     * The command signature.
     *
     * @var string
     */
    protected $signature = 'parse
                            {--grammar= : Path to the grammar file}
                            {--i|input= : Path to the source file to parse}
                            {--o|output= : Write the JSON payload to a file}
                            {--tree-format= : Export parse tree format: dot}
                            {--tree-output= : Write the parse tree export to a file}
                            {--grammar-format=auto : Grammar format: auto, peg, or cleanpeg}
                            {--start-rule= : Override the grammar start rule}
                            {--query= : AST selector used to filter the output nodes}
                            {--json-style=simple : JSON style: full or simple}';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Parse a source file and export matching AST nodes as JSON.';

    /**
     * Executes the parse command.
     */
    public function handle(): int
    {
        try {
            $grammarPath = $this->normalizeRequiredPath($this->option('grammar'), 'grammar');
            $inputPath = $this->normalizeRequiredPath($this->option('input'), 'input');
            $outputPath = $this->normalizeOptionalString($this->option('output'));
            $treeFormat = $this->normalizeTreeFormat($this->option('tree-format'));
            $treeOutputPath = $this->normalizeOptionalString($this->option('tree-output'));
            $grammarFormat = $this->normalizeGrammarFormat((string) $this->option('grammar-format'));
            $startRule = $this->normalizeOptionalString($this->option('start-rule'));
            $query = $this->normalizeOptionalString($this->option('query'));
            $jsonStyle = $this->normalizeJsonStyle((string) $this->option('json-style'));

            $this->validateTreeExportOptions($treeFormat, $treeOutputPath);

            $grammar = $this->loadGrammar($grammarPath, $grammarFormat, $startRule);
            $source = $this->loadFile($inputPath);
            $result = $grammar->parse($source, $startRule);

            if (!$result->isSuccess() || $result->node() === null) {
                $this->renderParseFailure($grammarPath, $inputPath, $grammarFormat, $startRule, $result->error());

                return self::FAILURE;
            }

            $document = new ParsedDocument($grammar, $source, $result->node());
            $this->writeTreeExport($document, $treeFormat, $treeOutputPath);
            $nodes = $query !== null ? $document->query($query)->all() : [$document->root()];
            $payload = $this->buildPayload(
                jsonStyle: $jsonStyle,
                grammarPath: $grammarPath,
                grammarFormat: $grammarFormat,
                startRule: $grammar->startRule(),
                inputPath: $inputPath,
                sourceLength: strlen($source),
                finalOffset: $result->finalOffset(),
                matchedText: $result->matchedText(),
                query: $query,
                nodes: $nodes,
            );
            $this->writeJsonPayload($payload, $outputPath);

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
            throw new \InvalidArgumentException(sprintf('Missing required %s path.', $name));
        }

        return $value;
    }

    /**
     * Loads a grammar from disk using the requested format.
     */
    private function loadGrammar(string $path, string $format, ?string $startRule): Grammar
    {
        return match ($format) {
            'peg' => $this->loadPegGrammar($path),
            'cleanpeg' => $this->loadCleanPegGrammar($path, $startRule),
            default => throw new \InvalidArgumentException(sprintf('Unsupported grammar format "%s".', $format)),
        };
    }

    /**
     * Loads a classic PEG grammar file.
     */
    private function loadPegGrammar(string $path): Grammar
    {
        return (new PegGrammarLoader())->fromFile($path);
    }

    /**
     * Loads a CleanPeg grammar file.
     */
    private function loadCleanPegGrammar(string $path, ?string $startRule): Grammar
    {
        return (new CleanPegGrammarLoader())->fromFile($path, $startRule);
    }

    /**
     * Reads the full contents of a file.
     */
    private function loadFile(string $path): string
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \InvalidArgumentException(sprintf('Unable to read file: %s', $path));
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
     * Normalizes the tree export format option.
     */
    private function normalizeTreeFormat(mixed $value): ?string
    {
        $normalized = $this->normalizeOptionalString($value);
        if ($normalized === null) {
            return null;
        }

        return strtolower(trim($normalized));
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
     * Normalizes the JSON style option.
     */
    private function normalizeJsonStyle(string $style): string
    {
        $normalized = strtolower(trim($style));

        if (!in_array($normalized, ['full', 'simple'], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported JSON style "%s". Use "full" or "simple".', $style));
        }

        return $normalized;
    }

    /**
     * Builds the JSON payload for the selected output style.
     *
     * @param list<\EmanueleCoppola\PHPeg\Ast\AstNode> $nodes
     * @return array<string, mixed>
     */
    private function buildPayload(
        string $jsonStyle,
        string $grammarPath,
        string $grammarFormat,
        string $startRule,
        string $inputPath,
        int $sourceLength,
        int $finalOffset,
        string $matchedText,
        ?string $query,
        array $nodes,
    ): array {
        $exporter = new AstJsonExporter();

        if ($jsonStyle === 'simple') {
            return [
                'success' => true,
                'matches' => $exporter->exportCompactNodes($nodes),
            ];
        }

        return [
            'success' => true,
            'grammar' => [
                'path' => $grammarPath,
                'format' => $grammarFormat,
                'startRule' => $startRule,
            ],
            'input' => [
                'path' => $inputPath,
                'length' => $sourceLength,
            ],
            'parse' => [
                'finalOffset' => $finalOffset,
                'matchedText' => $matchedText,
            ],
            'query' => [
                'selector' => $query,
                'count' => count($nodes),
            ],
            'matches' => $exporter->exportNodes($nodes),
        ];
    }

    /**
     * Writes the JSON payload to stdout or to a file path.
     *
     * @param array<string, mixed> $payload
     */
    private function writeJsonPayload(array $payload, ?string $outputPath): void
    {
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if ($json === false) {
            throw new \RuntimeException('Unable to encode JSON output.');
        }

        if ($outputPath === null) {
            $this->output->write($json . PHP_EOL);

            return;
        }

        if (file_put_contents($outputPath, $json . PHP_EOL) === false) {
            throw new \RuntimeException(sprintf('Unable to write JSON output to %s.', $outputPath));
        }
    }

    /**
     * Validates the tree export options before any output is written.
     */
    private function validateTreeExportOptions(?string $format, ?string $outputPath): void
    {
        if ($format === null && $outputPath === null) {
            return;
        }

        if ($format === null) {
            throw new \InvalidArgumentException('Pass --tree-format=dot when using --tree-output.');
        }

        if ($outputPath === null) {
            throw new \InvalidArgumentException('Pass --tree-output=path/to/tree.dot when using --tree-format=dot.');
        }

        if ($format !== 'dot') {
            throw new \InvalidArgumentException(sprintf('Unsupported tree format "%s". Use "dot".', $format));
        }
    }

    /**
     * Writes the parse tree export to a file when requested.
     */
    private function writeTreeExport(ParsedDocument $document, ?string $format, ?string $outputPath): void
    {
        $this->validateTreeExportOptions($format, $outputPath);

        if ($format === null && $outputPath === null) {
            return;
        }

        if ($outputPath === null) {
            return;
        }

        $dot = (new AstDotExporter())->export($document->root());

        if (file_put_contents($outputPath, $dot) === false) {
            throw new \RuntimeException(sprintf('Unable to write tree export to %s.', $outputPath));
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
            default => throw new \InvalidArgumentException(sprintf(
                'Unable to infer grammar format from "%s". Pass --grammar-format=peg or --grammar-format=cleanpeg.',
                $path,
            )),
        };
    }

    /**
     * Renders a parse failure as a clean CLI error.
     */
    private function renderParseFailure(string $grammarPath, string $inputPath, string $grammarFormat, ?string $startRule, ?ParseError $error): void
    {
        $lines = [
            'ERROR',
            $error?->message() ?? 'Unable to parse input.',
            '',
            'Context:',
            sprintf('grammar: %s', $grammarPath),
            sprintf('input: %s', $inputPath),
            sprintf('format: %s', $grammarFormat),
        ];

        if ($startRule !== null) {
            $lines[] = sprintf('start rule: %s', $startRule);
        }

        $this->renderErrorBox($lines);
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

        return 'Unexpected error: ' . $throwable->getMessage();
    }

    /**
     * Returns a short actionable hint for known exceptions.
     */
    private function friendlyErrorHint(Throwable $throwable): ?string
    {
        $message = $throwable->getMessage();

        if (str_contains($message, 'Missing required grammar path.')) {
            return 'Pass --grammar=path/to/file.cleanpeg and -i path/to/input.txt.';
        }

        if (str_contains($message, 'Missing required input path.')) {
            return 'Pass -i path/to/input.txt or --input=path/to/input.txt.';
        }

        if (str_contains($message, 'Unable to infer grammar format')) {
            return 'Use --grammar-format=cleanpeg or --grammar-format=peg.';
        }

        if (str_contains($message, 'Unsupported grammar format')) {
            return 'Use --grammar-format=auto, cleanpeg, or peg.';
        }

        if (str_contains($message, 'Unsupported JSON style')) {
            return 'Use --json-style=full or --json-style=simple.';
        }

        if (str_contains($message, 'Unsupported tree format')) {
            return 'Use --tree-format=dot.';
        }

        if (str_contains($message, 'Pass --tree-output=path/to/tree.dot when using --tree-format=dot.')) {
            return 'Pass --tree-output=path/to/tree.dot together with --tree-format=dot.';
        }

        if (str_contains($message, 'Pass --tree-format=dot when using --tree-output.')) {
            return 'Pass --tree-format=dot together with --tree-output=path/to/tree.dot.';
        }

        if ($throwable instanceof GrammarSyntaxError) {
            return 'Check the grammar file syntax around the reported location.';
        }

        return null;
    }

}
