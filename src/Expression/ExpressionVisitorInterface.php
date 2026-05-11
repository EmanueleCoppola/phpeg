<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

/**
 * Visitor contract for PEG expression graph traversal.
 */
interface ExpressionVisitorInterface
{
    /**
     * Visits any expression kind.
     */
    public function visitExpression(ExpressionInterface $expression, int $depth): void;

    public function visitAnyCharacter(AnyCharacterExpression $expression, int $depth): void;

    public function visitAndPredicate(AndPredicateExpression $expression, int $depth): void;

    public function visitChoice(ChoiceExpression $expression, int $depth): void;

    public function visitCharClass(CharClassExpression $expression, int $depth): void;

    public function visitEndOfInput(EndOfInputExpression $expression, int $depth): void;

    public function visitLake(LakeExpression $expression, int $depth): void;

    public function visitLiteral(LiteralExpression $expression, int $depth): void;

    public function visitNamedCapture(NamedCaptureExpression $expression, int $depth): void;

    public function visitNotPredicate(NotPredicateExpression $expression, int $depth): void;

    public function visitOneOrMore(OneOrMoreExpression $expression, int $depth): void;

    public function visitOptional(OptionalExpression $expression, int $depth): void;

    public function visitRegex(RegexExpression $expression, int $depth): void;

    public function visitRuleReference(RuleReferenceExpression $expression, int $depth): void;

    public function visitSequence(SequenceExpression $expression, int $depth): void;

    public function visitSpanEqual(SpanEqualExpression $expression, int $depth): void;

    public function visitSpanNotEqual(SpanNotEqualExpression $expression, int $depth): void;

    public function visitZeroOrMore(ZeroOrMoreExpression $expression, int $depth): void;
}
