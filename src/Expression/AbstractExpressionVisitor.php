<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

/**
 * Convenience base class for PEG expression visitors.
 */
abstract class AbstractExpressionVisitor implements ExpressionVisitorInterface
{
    /**
     * Visits any expression kind.
     */
    public function visitExpression(ExpressionInterface $expression, int $depth): void
    {
    }

    /**
     * Visits a single-character wildcard expression.
     */
    public function visitAnyCharacter(AnyCharacterExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a positive lookahead predicate.
     */
    public function visitAndPredicate(AndPredicateExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits an ordered-choice expression.
     */
    public function visitChoice(ChoiceExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a character class expression.
     */
    public function visitCharClass(CharClassExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits an end-of-input expression.
     */
    public function visitEndOfInput(EndOfInputExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a lake expression.
     */
    public function visitLake(LakeExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a literal expression.
     */
    public function visitLiteral(LiteralExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a named capture expression.
     */
    public function visitNamedCapture(NamedCaptureExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a negative lookahead predicate.
     */
    public function visitNotPredicate(NotPredicateExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a one-or-more repetition expression.
     */
    public function visitOneOrMore(OneOrMoreExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits an optional expression.
     */
    public function visitOptional(OptionalExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a regular expression.
     */
    public function visitRegex(RegexExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a rule reference expression.
     */
    public function visitRuleReference(RuleReferenceExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a sequence expression.
     */
    public function visitSequence(SequenceExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits an equal-span expression.
     */
    public function visitSpanEqual(SpanEqualExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a not-equal-span expression.
     */
    public function visitSpanNotEqual(SpanNotEqualExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }

    /**
     * Visits a zero-or-more repetition expression.
     */
    public function visitZeroOrMore(ZeroOrMoreExpression $expression, int $depth): void
    {
        $this->visitExpression($expression, $depth);
    }
}
