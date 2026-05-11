<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Parser;

use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;
use EmanueleCoppola\PHPeg\Expression\ExpressionVisitorInterface;
use EmanueleCoppola\PHPeg\Parser\InputBuffer;
use EmanueleCoppola\PHPeg\Parser\Packrat\PackratParseContext;
use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Parser\ParserOptions;
use EmanueleCoppola\PHPeg\Result\MatchResult;
use PHPUnit\Framework\TestCase;

class ParseContextTest extends TestCase
{
    /**
     * Verifies grammar/input accessors and memoized rule matching.
     */
    public function testMatchesRulesAndMemoizesResults(): void
    {
        $grammar = $this->memoizationGrammar();
        $context = new PackratParseContext($grammar, new InputBuffer('a'));
        $first = $context->matchRule('Start', 0);
        $second = $context->matchRule('Start', 0);
        self::assertSame($grammar, $context->grammar());
        self::assertSame('a', $context->input()->text());
        self::assertSame($first, $second);
    }

    /**
     * Builds a useful error when a rule is missing or fails.
     */
    public function testBuildsFailureErrors(): void
    {
        $builder = GrammarBuilder::create();
        $grammar = $builder
            ->grammar('Start')
            ->rule('Start', $builder->literal('a'))
            ->build();
        $context = new PackratParseContext($grammar, new InputBuffer('b'));

        self::assertNull($context->matchRule('Missing', 0));
        self::assertStringContainsString('rule <Missing>', $context->error()->message());
    }

    /**
     * Verifies rule memoization can be disabled explicitly.
     */
    public function testCanDisableMemoization(): void
    {
        $expression = new CountingLiteralExpression('a');
        $grammar = GrammarBuilder::create()
            ->grammar('Start')
            ->rule('Start', $expression)
            ->build();
        $context = new PackratParseContext($grammar, new InputBuffer('a'), new ParserOptions(memoizationEnabled: false));

        $context->matchRule('Start', 0);
        $context->matchRule('Start', 0);

        self::assertSame(2, $expression->calls());
    }

    /**
     * Verifies the memoization cache limit evicts older entries.
     */
    public function testHonorsMemoizationCacheLimit(): void
    {
        $expression = new CountingLiteralExpression('a');
        $grammar = GrammarBuilder::create()
            ->grammar('Start')
            ->rule('Start', $expression)
            ->build();
        $context = new PackratParseContext($grammar, new InputBuffer('a'), new ParserOptions(maxCacheEntries: 1));

        $context->matchRule('Start', 0);
        $context->matchRule('Start', 1);
        $context->matchRule('Start', 0);

        self::assertSame(3, $expression->calls());
    }

    /**
     * Returns a small grammar used for memoization checks.
     */
    private function memoizationGrammar(): \EmanueleCoppola\PHPeg\Grammar\Grammar
    {
        $builder = GrammarBuilder::create();

        return $builder
            ->grammar('Start')
            ->rule('Start', $builder->seq($builder->literal('a'), $builder->eof()))
            ->build();
    }
}

/**
 * Counts literal expression invocations for parser option tests.
 */
class CountingLiteralExpression implements ExpressionInterface
{
    private int $calls = 0;

    /**
     * Creates the counting expression.
     */
    public function __construct(
        private readonly string $literal,
    ) {
    }

    /**
     * Returns the number of times the expression was evaluated.
     */
    public function calls(): int
    {
        return $this->calls;
    }

    /**
     * Matches the configured literal.
     */
    public function match(ParseContext $context, int $offset): ?MatchResult
    {
        $this->calls++;

        if (substr_compare($context->input()->text(), $this->literal, $offset, strlen($this->literal)) !== 0) {
            $context->recordFailure($offset, $this->describe());

            return null;
        }

        return new MatchResult($offset, $offset + strlen($this->literal));
    }

    /**
     * Returns the literal description used in errors.
     */
    public function describe(): string
    {
        return sprintf('"%s"', $this->literal);
    }

    /**
     * @inheritDoc
     */
    public function isStateful(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function accept(ExpressionVisitorInterface $visitor, int $depth = 0): void
    {
        $visitor->visitExpression($this, $depth);
    }
}
