<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Expression;

/**
 * Shared base class for PEG expressions.
 */
abstract class AbstractExpression implements ExpressionInterface
{
    /**
     * @inheritDoc
     */
    public function isStateful(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function accept(ExpressionVisitorInterface $visitor, int $depth = 0): void
    {
        match (true) {
            $this instanceof AnyCharacterExpression => $visitor->visitAnyCharacter($this, $depth),
            $this instanceof AndPredicateExpression => $visitor->visitAndPredicate($this, $depth),
            $this instanceof ChoiceExpression => $visitor->visitChoice($this, $depth),
            $this instanceof CharClassExpression => $visitor->visitCharClass($this, $depth),
            $this instanceof EndOfInputExpression => $visitor->visitEndOfInput($this, $depth),
            $this instanceof LakeExpression => $visitor->visitLake($this, $depth),
            $this instanceof LiteralExpression => $visitor->visitLiteral($this, $depth),
            $this instanceof NamedCaptureExpression => $visitor->visitNamedCapture($this, $depth),
            $this instanceof NotPredicateExpression => $visitor->visitNotPredicate($this, $depth),
            $this instanceof OneOrMoreExpression => $visitor->visitOneOrMore($this, $depth),
            $this instanceof OptionalExpression => $visitor->visitOptional($this, $depth),
            $this instanceof RegexExpression => $visitor->visitRegex($this, $depth),
            $this instanceof RuleReferenceExpression => $visitor->visitRuleReference($this, $depth),
            $this instanceof SequenceExpression => $visitor->visitSequence($this, $depth),
            $this instanceof SpanEqualExpression => $visitor->visitSpanEqual($this, $depth),
            $this instanceof SpanNotEqualExpression => $visitor->visitSpanNotEqual($this, $depth),
            $this instanceof ZeroOrMoreExpression => $visitor->visitZeroOrMore($this, $depth),
            default => $visitor->visitExpression($this, $depth),
        };
    }
}
