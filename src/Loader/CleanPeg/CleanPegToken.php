<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Loader\CleanPeg;

/**
 * Represents a token in CleanPeg grammar source.
 */
class CleanPegToken
{
    /**
     * Initializes a new CleanPegToken instance.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $lexeme,
        public readonly int $line,
        public readonly int $column,
        public readonly ?bool $ignoreCase = null,
    ) {
    }
}
