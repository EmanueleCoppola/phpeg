<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Expression;

use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;
use PHPUnit\Framework\TestCase;

class BindingExpressionTest extends TestCase
{
    /**
     * Verifies that named captures store and reuse the same text value within a rule.
     */
    public function testNamedCaptureRequiresTheSameValue(): void
    {
        $builder = GrammarBuilder::create();
        $grammar = $builder
            ->grammar('Start')
            ->rule('Start', $builder->seq(
                $builder->capture('word', $builder->ref('Word')),
                $builder->literal(' '),
                $builder->capture('word', $builder->ref('Word')),
                $builder->eof(),
            ))
            ->rule('Word', $builder->regex('[a-z]+'))
            ->build();

        self::assertTrue($grammar->parse('foo foo')->isSuccess());
        self::assertFalse($grammar->parse('foo bar')->isSuccess());
    }

    /**
     * Verifies that same-span equality accepts operands ending at the same offset.
     */
    public function testSameSpanRequiresMatchingEndOffset(): void
    {
        $builder = GrammarBuilder::create();
        $grammar = $builder
            ->grammar('Start')
            ->rule('Start', $builder->seq(
                $builder->sameSpan(
                    $builder->choice(
                        $builder->literal('ab'),
                        $builder->literal('abc'),
                    ),
                    $builder->literal('ab'),
                ),
                $builder->eof(),
            ))
            ->build();

        self::assertTrue($grammar->parse('ab')->isSuccess());
        self::assertFalse($grammar->parse('abc')->isSuccess());
    }

    /**
     * Verifies that different-span equality rejects operands ending at the same offset.
     */
    public function testDifferentSpanRequiresDifferentEndOffset(): void
    {
        $builder = GrammarBuilder::create();
        $grammar = $builder
            ->grammar('Start')
            ->rule('Start', $builder->seq(
                $builder->differentSpan(
                    $builder->choice(
                        $builder->literal('abc'),
                        $builder->literal('ab'),
                    ),
                    $builder->literal('ab'),
                ),
                $builder->eof(),
            ))
            ->build();

        self::assertFalse($grammar->parse('ab')->isSuccess());
        self::assertTrue($grammar->parse('abc')->isSuccess());
    }
}
