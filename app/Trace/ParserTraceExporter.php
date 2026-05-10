<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\App\Trace;

use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Result\ParseResult;

/**
 * Builds the JSON document consumed by the trace viewer.
 */
class ParserTraceExporter
{
    /**
     * Initializes a new ParserTraceExporter instance.
     */
    public function __construct(
        private readonly GrammarTraceExporter $grammarExporter = new GrammarTraceExporter(),
    ) {
    }

    /**
     * Builds the full trace document payload.
     *
     * @param list<array<string, mixed>> $steps
     * @return array<string, mixed>
     */
    public function export(
        Grammar $grammar,
        string $grammarPath,
        string $grammarFormat,
        ?string $grammarSource,
        string $inputPath,
        string $inputSource,
        array $steps,
        ?ParseResult $result = null,
        ?string $generatedAt = null,
    ): array {
        return [
            'schema' => 'phpeg.trace.v1',
            'generatedAt' => $generatedAt ?? gmdate('c'),
            'grammar' => [
                'path' => $grammarPath,
                'format' => $grammarFormat,
                'startRule' => $grammar->startRule(),
                'source' => $grammarSource,
                'snapshot' => $this->grammarExporter->export($grammar),
            ],
            'input' => [
                'path' => $inputPath,
                'source' => $inputSource,
                'length' => strlen($inputSource),
            ],
            'parse' => [
                'success' => $result?->isSuccess(),
                'finalOffset' => $result?->finalOffset(),
                'matchedText' => $result?->matchedText(),
                'error' => $this->exportError($result),
            ],
            'steps' => $steps,
        ];
    }

    /**
     * Encodes the trace document as pretty JSON.
     *
     * @param array<string, mixed> $document
     */
    public function encode(array $document): string
    {
        $json = json_encode(
            $document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if ($json === false) {
            throw new \RuntimeException('Unable to encode trace JSON.');
        }

        return $json;
    }

    /**
     * Serializes the parse error when the parse fails.
     *
     * @return array<string, mixed>|null
     */
    private function exportError(?ParseResult $result): ?array
    {
        $error = $result?->error();
        if ($error === null) {
            return null;
        }

        return [
            'offset' => $error->offset(),
            'line' => $error->line(),
            'column' => $error->column(),
            'expected' => $error->expected(),
            'snippet' => $error->snippet(),
            'message' => $error->message(),
        ];
    }
}
