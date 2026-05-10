<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Parser;

use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;
use EmanueleCoppola\PHPeg\App\Trace\ParserTraceRecorder;
use EmanueleCoppola\PHPeg\Parser\ParserOptions;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    /**
     * Verifies successful parsing and parse result metadata.
     */
    public function testParsesSuccessfulInput(): void
    {
        $grammar = $this->simpleGrammar();
        $result = $grammar->parse('a');

        self::assertTrue($result->isSuccess());
        self::assertSame('a', $result->matchedText());
        self::assertSame(1, $result->finalOffset());
        self::assertSame('Start', $result->node()?->name());
    }

    /**
     * Parses left-recursive grammars by switching to the bottom-up path automatically.
     */
    public function testAutoDetectsAndParsesLeftRecursion(): void
    {
        $grammar = $this->leftRecursiveGrammar();
        $result = $grammar->parse('aaa');

        self::assertTrue($grammar->requiresLeftRecursion());
        self::assertTrue($result->isSuccess());
        self::assertSame('aaa', $result->matchedText());
    }

    /**
     * Verifies optimized error tracking remains opt-in.
     */
    public function testSupportsOptimizedErrorTrackingAsAnOption(): void
    {
        $builder = GrammarBuilder::create();
        $grammar = $builder
            ->grammar('Start')
            ->rule('Start', $builder->choice($builder->literal('a'), $builder->literal('b')))
            ->build();

        $detailed = $grammar->parse('c');
        $optimized = $grammar->parse('c', options: new ParserOptions(optimizeErrors: true));

        self::assertSame(['"a"', '"b"'], $detailed->error()?->expected());
        self::assertCount(1, $optimized->error()?->expected() ?? []);
    }

    /**
     * Verifies tracing stays opt-in and does not affect parse results.
     */
    public function testSupportsAnOptionalTraceRecorder(): void
    {
        $grammar = $this->simpleGrammar();
        $recorder = new ParserTraceRecorder();

        $withoutTrace = $grammar->parse('a');
        $withTrace = $grammar->parse('a', traceRecorder: $recorder);

        self::assertTrue($withoutTrace->isSuccess());
        self::assertTrue($withTrace->isSuccess());
        self::assertSame($withoutTrace->matchedText(), $withTrace->matchedText());
        self::assertGreaterThan(0, count($recorder->steps()));
    }

    /**
     * Builds a grammar that accepts a single `a`.
     */
    private function simpleGrammar(): \EmanueleCoppola\PHPeg\Grammar\Grammar
    {
        $builder = GrammarBuilder::create();

        return $builder
            ->grammar('Start')
            ->rule('Start', $builder->seq($builder->literal('a'), $builder->eof()))
            ->build();
    }

    /**
     * Builds a grammar that triggers direct left recursion.
     */
    private function leftRecursiveGrammar(): \EmanueleCoppola\PHPeg\Grammar\Grammar
    {
        $builder = GrammarBuilder::create();

        return $builder
            ->grammar('Start')
            ->rule('Start', $builder->choice(
                $builder->seq($builder->ref('Start'), $builder->literal('a')),
                $builder->literal('a'),
            ))
            ->build();
    }
}
