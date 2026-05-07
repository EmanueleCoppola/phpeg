<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Captures a matched text slice under a stable name and enforces equality on reuse.
 */
class NamedCaptureExpression extends AbstractExpression
{
    /**
     * Initializes a new NamedCaptureExpression instance.
     */
    public function __construct(
        private readonly string $name,
        private readonly ExpressionInterface $expression,
        private readonly ?bool $ignoreCase = null,
    ) {
    }

    /**
     * Returns the capture name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the wrapped operand.
     */
    public function expression(): ExpressionInterface
    {
        return $this->expression;
    }

    /**
     * Returns the capture case-sensitivity override, if any.
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
        $runner = function () use ($context, $offset): ?MatchResult {
            $snapshot = $context->snapshotBindings();
            $result = $context->matchExpression($this->expression, $offset);
            if ($result === null) {
                $context->restoreBindings($snapshot);
                $context->recordFailure($offset, $this->describe());

                return null;
            }

            $value = trim($context->input()->slice($offset, $result->endOffset()));
            $binding = $context->binding($this->name);
            if ($binding === null) {
                $context->setBinding($this->name, $value);

                return $result;
            }

            if (!$this->compareBinding($binding, $value, $context)) {
                $context->restoreBindings($snapshot);
                $context->recordFailure($offset, $this->describe());

                return null;
            }

            return $result;
        };

        if ($this->ignoreCase !== null) {
            return $context->withCaseSensitivity($this->ignoreCase, $runner);
        }

        return $runner();
    }

    /**
     * Compares the capture binding against a later match.
     */
    private function compareBinding(string $binding, string $value, ParseContext $context): bool
    {
        $ignoreCase = $this->ignoreCase ?? $context->currentIgnoreCase();

        return $ignoreCase ? strcasecmp($binding, $value) === 0 : $binding === $value;
    }

    /**
     * @inheritDoc
     */
    public function describe(): string
    {
        return sprintf('%s@%s', $this->name, $this->expression->describe());
    }

    /**
     * @inheritDoc
     */
    public function isStateful(): bool
    {
        return true;
    }
}
