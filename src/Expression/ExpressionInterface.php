<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Contract for all PEG expressions.
 */
interface ExpressionInterface
{
    /**
     * Attempts to match the expression at the provided offset.
     */
    public function match(ParseContext $context, int $offset): ?MatchResult;

    /**
     * Returns a short human-readable description used in diagnostics.
     */
    public function describe(): string;

    /**
     * Returns whether the expression depends on parser state that can change across backtracking.
     */
    public function isStateful(): bool;

}
