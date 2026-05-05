<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Matches zero or more repetitions of an expression.
 */
class ZeroOrMoreExpression extends AbstractExpression
{
    /**
     * Initializes a new ZeroOrMoreExpression instance.
     */
    private readonly bool $stateful;

    public function __construct(
        private readonly ExpressionInterface $expression,
    ) {
        $this->stateful = $expression->isStateful();
    }

    /**
     * Returns the repeated operand.
     */
    public function expression(): ExpressionInterface
    {
        return $this->expression;
    }

    /**
     * @inheritDoc
     */
    public function match(ParseContext $context, int $offset): ?MatchResult
    {
        $nodes = [];
        $cursor = $offset;

        while (true) {
            $result = $context->matchExpression($this->expression, $cursor);
            if ($result === null || $result->endOffset() === $cursor) {
                break;
            }

            $cursor = $result->endOffset();
            foreach ($result->nodes() as $node) {
                $nodes[] = $node;
            }
        }

        return new MatchResult($offset, $cursor, $nodes);
    }

    /**
     * @inheritDoc
     */
    public function describe(): string
    {
        return $this->expression->describe() . '*';
    }

    /**
     * @inheritDoc
     */
    public function isStateful(): bool
    {
        return $this->stateful;
    }
}
