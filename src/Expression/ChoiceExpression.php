<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Matches the first successful alternative.
 */
class ChoiceExpression extends AbstractExpression
{
    /**
     * @param list<ExpressionInterface> $alternatives
     */
    private readonly bool $stateful;

    public function __construct(
        private readonly array $alternatives,
    ) {
        $stateful = false;
        foreach ($alternatives as $alternative) {
            if ($alternative->isStateful()) {
                $stateful = true;
                break;
            }
        }

        $this->stateful = $stateful;
    }

    /**
     * @return list<ExpressionInterface>
     */
    public function alternatives(): array
    {
        return $this->alternatives;
    }

    /**
     * @inheritDoc
     */
    public function match(ParseContext $context, int $offset): ?MatchResult
    {
        $snapshot = $this->stateful ? $context->snapshotBindings() : null;
        foreach ($this->alternatives as $alternative) {
            $result = $context->matchExpression($alternative, $offset);
            if ($result !== null) {
                return $result;
            }

            if ($snapshot !== null) {
                $context->restoreBindings($snapshot);
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function describe(): string
    {
        return 'choice';
    }

    /**
     * @inheritDoc
     */
    public function isStateful(): bool
    {
        return $this->stateful;
    }
}
