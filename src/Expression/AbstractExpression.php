<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

/**
 * Shared base class for PEG expressions.
 */
abstract class AbstractExpression implements ExpressionInterface
{
    /**
     * @inheritDoc
     */
    public function isStateful(): bool
    {
        return false;
    }
}
