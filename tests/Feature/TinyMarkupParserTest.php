<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Feature;

use EmanueleCoppola\PHPeg\Loader\CleanPeg\CleanPegGrammarLoader;
use PHPUnit\Framework\TestCase;

class TinyMarkupParserTest extends TestCase
{
    /**
     * Verifies the tiny-markup grammar parses valid nested content and preserves tag equality.
     */
    public function testParsesNestedTinyMarkup(): void
    {
        $grammar = $this->grammar();
        $result = $grammar->parseDocument('<incident>opening note<summary>database timeout</summary><timeline><event>12:03 slow query</event><event>12:05 retry</event></timeline><resolution>restart node</resolution></incident>');

        self::assertSame('Document', $result->root()->name());
        self::assertSame(6, $result->query('Element')->count());
        self::assertSame(5, $result->query('Text')->count());
    }

    /**
     * Verifies the tiny-markup grammar rejects mismatched closing tags.
     */
    public function testRejectsMismatchedClosingTags(): void
    {
        $grammar = $this->grammar();
        $result = $grammar->parse('<incident>opening note</other>');

        self::assertFalse($result->isSuccess());
    }

    /**
     * Loads the tiny-markup grammar from the example CleanPeg file.
     */
    private function grammar(): \EmanueleCoppola\PHPeg\Grammar\Grammar
    {
        return (new CleanPegGrammarLoader(skipPattern: null))->fromFile(
            __DIR__ . '/TinyMarkupParserTest/tiny-markup.cleanpeg',
            startRule: 'Document',
        );
    }
}
