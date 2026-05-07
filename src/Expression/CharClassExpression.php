<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

use InvalidArgumentException;
use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Matches a single character using a PEG character class.
 */
class CharClassExpression extends AbstractExpression
{
    private readonly string $regex;

    private readonly string $regexIgnoreCase;

    private readonly ?bool $ignoreCase;

    /**
     * Initializes a new CharClassExpression instance.
     */
    public function __construct(
        private readonly string $pattern,
        ?bool $ignoreCase = null,
    ) {
        if (!preg_match('~^\[(?:\\\\.|[^\]])+\]$~', $pattern)) {
            throw new InvalidArgumentException(sprintf('Invalid character class pattern: %s', $pattern));
        }

        $this->regex = sprintf('~\G%s~Au', $pattern);
        $this->regexIgnoreCase = sprintf('~\G%s~Aiu', $pattern);
        $this->ignoreCase = $ignoreCase;
    }

    /**
     * Returns the original character class pattern.
     */
    public function pattern(): string
    {
        return $this->pattern;
    }

    /**
     * Returns the character class case-sensitivity override, if any.
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
        $regex = $ignoreCase ? $this->regexIgnoreCase : $this->regex;

        if (preg_match($regex, $context->input()->text(), $matches, 0, $offset) !== 1) {
            $context->recordFailure($offset, $this->describe());

            return null;
        }

        return new MatchResult($offset, $offset + strlen($matches[0]));
    }

    /**
     * @inheritDoc
     */
    public function describe(): string
    {
        return $this->pattern;
    }
}
