<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

use InvalidArgumentException;
use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Matches an anchored PCRE pattern.
 */
class RegexExpression extends AbstractExpression
{
    private readonly string $regex;

    private readonly string $regexIgnoreCase;

    private readonly string $description;

    private readonly bool $canMatchEmpty;

    private readonly ?bool $ignoreCase;

    /**
     * Initializes a new RegexExpression instance.
     */
    public function __construct(
        private readonly string $pattern,
        ?bool $ignoreCase = null,
    ) {
        $regex = sprintf('~\G(?:%s)~Au', $pattern);
        $regexIgnoreCase = sprintf('~\G(?:%s)~Aiu', $pattern);
        set_error_handler(static fn (): bool => true);
        $isValid = preg_match($regex, '') !== false;
        $isValidIgnoreCase = preg_match($regexIgnoreCase, '') !== false;
        restore_error_handler();

        if (!$isValid || !$isValidIgnoreCase) {
            throw new InvalidArgumentException(sprintf('Invalid regex pattern: %s', $pattern));
        }

        $this->regex = $regex;
        $this->regexIgnoreCase = $regexIgnoreCase;
        $this->description = sprintf('regex(%s)', $pattern);
        $this->canMatchEmpty = preg_match($this->regex, '') === 1;
        $this->ignoreCase = $ignoreCase;
    }

    /**
     * Returns the original PCRE pattern.
     */
    public function pattern(): string
    {
        return $this->pattern;
    }

    /**
     * Returns the regex case-sensitivity override, if any.
     */
    public function ignoreCase(): ?bool
    {
        return $this->ignoreCase;
    }

    /**
     * Returns whether the regex can match the empty string.
     */
    public function canMatchEmpty(): bool
    {
        return $this->canMatchEmpty;
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
        return $this->description;
    }
}
