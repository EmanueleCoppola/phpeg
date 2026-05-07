<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Builder;

use InvalidArgumentException;
use EmanueleCoppola\PHPeg\Expression\AndPredicateExpression;
use EmanueleCoppola\PHPeg\Expression\AnyCharacterExpression;
use EmanueleCoppola\PHPeg\Expression\CharClassExpression;
use EmanueleCoppola\PHPeg\Expression\ChoiceExpression;
use EmanueleCoppola\PHPeg\Expression\EndOfInputExpression;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;
use EmanueleCoppola\PHPeg\Expression\LiteralExpression;
use EmanueleCoppola\PHPeg\Expression\LakeExpression;
use EmanueleCoppola\PHPeg\Expression\NamedCaptureExpression;
use EmanueleCoppola\PHPeg\Expression\NotPredicateExpression;
use EmanueleCoppola\PHPeg\Expression\OneOrMoreExpression;
use EmanueleCoppola\PHPeg\Expression\OptionalExpression;
use EmanueleCoppola\PHPeg\Expression\RegexExpression;
use EmanueleCoppola\PHPeg\Expression\SpanEqualExpression;
use EmanueleCoppola\PHPeg\Expression\SpanNotEqualExpression;
use EmanueleCoppola\PHPeg\Expression\RuleReferenceExpression;
use EmanueleCoppola\PHPeg\Expression\SequenceExpression;
use EmanueleCoppola\PHPeg\Expression\ZeroOrMoreExpression;
use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Grammar\Rule;

/**
 * Fluent grammar builder for declarative PHP grammar definitions.
 */
class GrammarBuilder
{
    /**
     * @var array<string, Rule>
     */
    private array $rules = [];

    /**
     * @var array<string, ExpressionInterface>
     */
    private array $lakeProfiles = [];

    private ?string $startRule = null;

    /**
     * Creates a new builder instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Sets the grammar start rule and returns the builder.
     */
    public function grammar(string $startRule): self
    {
        $this->startRule = $startRule;

        return $this;
    }

    /**
     * Adds or replaces a rule definition.
     *
     * @param bool $isWater Marks the rule as water so lake matching can consume it as background text.
     * @param ?bool $ignoreCase Sets the rule default case mode; null inherits from the active scope.
     */
    public function rule(string $name, ExpressionInterface $expression, bool $isWater = false, ?bool $ignoreCase = null): self
    {
        if ($this->startRule === null) {
            $this->startRule = $name;
        }

        $this->rules[$name] = new Rule($name, $expression, $isWater, $ignoreCase);

        return $this;
    }

    /**
     * Adds or replaces a named lake profile used by lake expressions with the same name.
     */
    public function lakeRule(string $name, ExpressionInterface $expression): self
    {
        $this->lakeProfiles[$name] = $expression;

        return $this;
    }

    /**
     * Builds the immutable grammar instance.
     */
    public function build(): Grammar
    {
        if ($this->startRule === null) {
            throw new InvalidArgumentException('Cannot build a grammar without a start rule.');
        }

        return new Grammar($this->rules, $this->startRule, $this->lakeProfiles);
    }

    /**
     * Creates a literal expression.
     *
     * @param ?bool $ignoreCase Sets the node case mode; null inherits from the active scope.
     */
    public function literal(string $literal, ?bool $ignoreCase = null): ExpressionInterface
    {
        return new LiteralExpression($literal, $ignoreCase);
    }

    /**
     * Creates a character class expression such as [0-9].
     *
     * @param ?bool $ignoreCase Sets the node case mode; null inherits from the active scope.
     */
    public function charClass(string $pattern, ?bool $ignoreCase = null): ExpressionInterface
    {
        return new CharClassExpression($pattern, $ignoreCase);
    }

    /**
     * Creates an anchored regex expression.
     *
     * @param ?bool $ignoreCase Sets the node case mode; null inherits from the active scope.
     */
    public function regex(string $pattern, ?bool $ignoreCase = null): ExpressionInterface
    {
        return new RegexExpression($pattern, $ignoreCase);
    }

    /**
     * Creates a sequence expression.
     */
    public function seq(ExpressionInterface ...$expressions): ExpressionInterface
    {
        return new SequenceExpression($expressions);
    }

    /**
     * Creates an ordered choice expression.
     */
    public function choice(ExpressionInterface ...$expressions): ExpressionInterface
    {
        return new ChoiceExpression($expressions);
    }

    /**
     * Creates a zero-or-more expression.
     */
    public function zeroOrMore(ExpressionInterface $expression): ExpressionInterface
    {
        return new ZeroOrMoreExpression($expression);
    }

    /**
     * Creates a one-or-more expression.
     */
    public function oneOrMore(ExpressionInterface $expression): ExpressionInterface
    {
        return new OneOrMoreExpression($expression);
    }

    /**
     * Creates an optional expression.
     */
    public function optional(ExpressionInterface $expression): ExpressionInterface
    {
        return new OptionalExpression($expression);
    }

    /**
     * Creates a rule reference expression.
     */
    public function ref(string $name): ExpressionInterface
    {
        return new RuleReferenceExpression($name);
    }

    /**
     * Creates a named capture expression that reuses the same value for repeated matches.
     *
     * @param ?bool $ignoreCase Sets the node case mode; null inherits from the active scope.
     */
    public function capture(string $name, ExpressionInterface $expression, ?bool $ignoreCase = null): ExpressionInterface
    {
        return new NamedCaptureExpression($name, $expression, $ignoreCase);
    }

    /**
     * Creates an any-character expression.
     */
    public function any(): ExpressionInterface
    {
        return new AnyCharacterExpression();
    }

    /**
     * Creates an end-of-input expression.
     */
    public function eof(): ExpressionInterface
    {
        return new EndOfInputExpression();
    }

    /**
     * Creates a lake expression that captures water up to the compiled stop set.
     */
    public function lake(?string $name = null, bool $capture = true): ExpressionInterface
    {
        return new LakeExpression($name, $capture);
    }

    /**
     * Creates a positive lookahead expression.
     */
    public function and(ExpressionInterface $expression): ExpressionInterface
    {
        return new AndPredicateExpression($expression);
    }

    /**
     * Creates a negative lookahead expression.
     */
    public function not(ExpressionInterface $expression): ExpressionInterface
    {
        return new NotPredicateExpression($expression);
    }

    /**
     * Creates an equality expression that requires both operands to end at the same offset.
     */
    public function sameSpan(ExpressionInterface $left, ExpressionInterface $right): ExpressionInterface
    {
        return new SpanEqualExpression($left, $right);
    }

    /**
     * Creates an inequality expression that requires both operands to end at different offsets.
     */
    public function differentSpan(ExpressionInterface $left, ExpressionInterface $right): ExpressionInterface
    {
        return new SpanNotEqualExpression($left, $right);
    }

    /**
     * Short alias for one-or-more.
     */
    public function one(ExpressionInterface $expression): ExpressionInterface
    {
        return $this->oneOrMore($expression);
    }

    /**
     * Short alias for zero-or-more.
     */
    public function many(ExpressionInterface $expression): ExpressionInterface
    {
        return $this->zeroOrMore($expression);
    }

    /**
     * Short alias for optional.
     */
    public function maybe(ExpressionInterface $expression): ExpressionInterface
    {
        return $this->optional($expression);
    }

    /**
     * Short alias for choice.
     */
    public function or(ExpressionInterface ...$expressions): ExpressionInterface
    {
        return $this->choice(...$expressions);
    }
}
