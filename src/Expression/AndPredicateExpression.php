<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Positive lookahead predicate.
 */
class AndPredicateExpression extends AbstractExpression
{
    /**
     * Initializes a new AndPredicateExpression instance.
     */
    private readonly bool $stateful;

    public function __construct(
        private readonly ExpressionInterface $expression,
    ) {
        $this->stateful = $expression->isStateful();
    }

    /**
     * Returns the looked-ahead operand.
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
        return $context->matchExpressionSilently($this->expression, $offset) !== null ? $context->emptyMatch($offset) : null;
    }

    /**
     * @inheritDoc
     */
    public function describe(): string
    {
        return '&' . $this->expression->describe();
    }

    /**
     * @inheritDoc
     */
    public function isStateful(): bool
    {
        return $this->stateful;
    }
}
