<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Grammar;

use EmanueleCoppola\PHPeg\Ast\AstNode;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;
use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Represents a named grammar rule.
 */
class Rule
{
    /**
     * Initializes a new Rule instance.
     *
     * @param bool $isWater Marks the rule as water for lake matching.
     */
    public function __construct(
        private readonly string $name,
        private readonly ExpressionInterface $expression,
        private readonly bool $isWater = false,
    ) {
        $this->stateful = $this->expression->isStateful();
    }

    /**
     * @var bool
     */
    private readonly bool $stateful;

    /**
     * Returns the rule name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the rule body.
     */
    public function expression(): ExpressionInterface
    {
        return $this->expression;
    }

    /**
     * Returns whether this rule is annotated as water.
     */
    public function isWater(): bool
    {
        return $this->isWater;
    }

    /**
     * Returns whether this rule depends on binding state.
     */
    public function isStateful(): bool
    {
        return $this->stateful;
    }

    /**
     * Matches this rule and wraps the resulting subtree into an AST node.
     */
    public function match(ParseContext $context, int $offset): ?MatchResult
    {
        if ($this->stateful) {
            $context->pushBindingFrame();
            try {
                $result = $this->expression->match($context, $offset);
            } finally {
                $context->popBindingFrame();
            }

            if ($result === null) {
                return null;
            }

            $node = new AstNode(
                $this->name,
                $context->options()->lazyNodeText() ? null : $context->input()->slice($offset, $result->endOffset()),
                $offset,
                $result->endOffset(),
                $result->nodes(),
                $this->isWater ? ['kind' => 'water'] : [],
                sourceBuffer: $context->options()->lazyNodeText() ? $context->input() : null,
            );

            return new MatchResult($offset, $result->endOffset(), [$node]);
        }

        $result = $this->expression->match($context, $offset);
        if ($result === null) {
            return null;
        }

        $node = new AstNode(
            $this->name,
            $context->options()->lazyNodeText() ? null : $context->input()->slice($offset, $result->endOffset()),
            $offset,
            $result->endOffset(),
            $result->nodes(),
            $this->isWater ? ['kind' => 'water'] : [],
            sourceBuffer: $context->options()->lazyNodeText() ? $context->input() : null,
        );

        return new MatchResult($offset, $result->endOffset(), [$node]);
    }
}
