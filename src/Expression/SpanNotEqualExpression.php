<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Matches two expressions only when they end at different offsets.
 */
class SpanNotEqualExpression extends AbstractExpression
{
    /**
     * Initializes a new SpanNotEqualExpression instance.
     */
    public function __construct(
        private readonly ExpressionInterface $left,
        private readonly ExpressionInterface $right,
    ) {
    }

    /**
     * Returns the left operand.
     */
    public function left(): ExpressionInterface
    {
        return $this->left;
    }

    /**
     * Returns the right operand.
     */
    public function right(): ExpressionInterface
    {
        return $this->right;
    }

    /**
     * @inheritDoc
     */
    public function match(ParseContext $context, int $offset): ?MatchResult
    {
        $snapshot = $context->snapshotBindings();
        $leftResult = $context->matchExpression($this->left, $offset);
        if ($leftResult === null) {
            $context->restoreBindings($snapshot);
            $context->recordFailure($offset, $this->describe());

            return null;
        }

        $rightResult = $context->matchExpression($this->right, $offset);
        if ($rightResult !== null && $leftResult->endOffset() === $rightResult->endOffset()) {
            $context->restoreBindings($snapshot);
            $context->recordFailure($offset, $this->describe());

            return null;
        }

        return $leftResult;
    }

    /**
     * @inheritDoc
     */
    public function describe(): string
    {
        return $this->left->describe() . ':!' . $this->right->describe();
    }

    /**
     * @inheritDoc
     */
    public function isStateful(): bool
    {
        return true;
    }
}
