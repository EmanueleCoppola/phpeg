<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Matches a sequence of expressions in order.
 */
class SequenceExpression extends AbstractExpression
{
    /**
     * @param list<ExpressionInterface> $expressions
     */
    private readonly bool $stateful;

    public function __construct(
        private readonly array $expressions,
    ) {
        $stateful = false;
        foreach ($expressions as $expression) {
            if ($expression->isStateful()) {
                $stateful = true;
                break;
            }
        }

        $this->stateful = $stateful;
    }

    /**
     * @return list<ExpressionInterface>
     */
    public function expressions(): array
    {
        return $this->expressions;
    }

    /**
     * @inheritDoc
     */
    public function match(ParseContext $context, int $offset): ?MatchResult
    {
        $snapshot = $this->stateful ? $context->snapshotBindings() : null;
        $nodes = [];
        $cursor = $offset;

        foreach ($this->expressions as $expression) {
            $result = $context->matchExpression($expression, $cursor);
            if ($result === null) {
                if ($snapshot !== null) {
                    $context->restoreBindings($snapshot);
                }

                return null;
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
        return 'sequence';
    }

    /**
     * @inheritDoc
     */
    public function isStateful(): bool
    {
        return $this->stateful;
    }
}
