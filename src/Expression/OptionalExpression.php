<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Matches an optional expression.
 */
class OptionalExpression extends AbstractExpression
{
    /**
     * Initializes a new OptionalExpression instance.
     */
    private readonly bool $stateful;

    public function __construct(
        private readonly ExpressionInterface $expression,
    ) {
        $this->stateful = $expression->isStateful();
    }

    /**
     * Returns the wrapped operand.
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
        $snapshot = $this->stateful ? $context->snapshotBindings() : null;
        $result = $context->matchExpression($this->expression, $offset);
        if ($result === null) {
            if ($snapshot !== null) {
                $context->restoreBindings($snapshot);
            }

            return $context->emptyMatch($offset);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function describe(): string
    {
        return $this->expression->describe() . '?';
    }

    /**
     * @inheritDoc
     */
    public function isStateful(): bool
    {
        return $this->stateful;
    }
}
