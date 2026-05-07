<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Matches an exact literal string.
 */
class LiteralExpression extends AbstractExpression
{
    private readonly int $length;

    private readonly string $description;

    private readonly ?bool $ignoreCase;

    /**
     * Initializes a new LiteralExpression instance.
     */
    public function __construct(
        private readonly string $literal,
        ?bool $ignoreCase = null,
    ) {
        $this->length = strlen($literal);
        $this->description = sprintf('"%s"', $literal);
        $this->ignoreCase = $ignoreCase;
    }

    /**
     * Returns the literal text.
     */
    public function literal(): string
    {
        return $this->literal;
    }

    /**
     * Returns the literal case-sensitivity override, if any.
     */
    public function ignoreCase(): ?bool
    {
        return $this->ignoreCase;
    }

    /**
     * @inheritDoc
     */
    public function match(ParseContext $context, int $offset): ?MatchResult
    {
        $ignoreCase = $this->ignoreCase ?? $context->currentIgnoreCase();

        if (substr_compare($context->input()->text(), $this->literal, $offset, $this->length, $ignoreCase) !== 0) {
            $context->recordFailure($offset, $this->describe());

            return null;
        }

        return new MatchResult($offset, $offset + $this->length);
    }

    /**
     * @inheritDoc
     */
    public function describe(): string
    {
        return $this->description;
    }
}
