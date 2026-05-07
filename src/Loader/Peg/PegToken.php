<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Loader\Peg;

/**
 * Represents a token emitted while lexing PEG grammar text.
 */
class PegToken
{
    /**
     * Initializes a new PegToken instance.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $lexeme,
        public readonly int $offset,
        public readonly ?bool $ignoreCase = null,
    ) {
    }
}
