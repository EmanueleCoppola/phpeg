<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Grammar;

use EmanueleCoppola\PHPeg\Expression\AbstractExpressionVisitor;
use EmanueleCoppola\PHPeg\Expression\AndPredicateExpression;
use EmanueleCoppola\PHPeg\Expression\ChoiceExpression;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;
use EmanueleCoppola\PHPeg\Expression\ExpressionVisitorInterface;
use EmanueleCoppola\PHPeg\Expression\LakeExpression;
use EmanueleCoppola\PHPeg\Expression\NamedCaptureExpression;
use EmanueleCoppola\PHPeg\Expression\NotPredicateExpression;
use EmanueleCoppola\PHPeg\Expression\OneOrMoreExpression;
use EmanueleCoppola\PHPeg\Expression\OptionalExpression;
use EmanueleCoppola\PHPeg\Expression\RegexExpression;
use EmanueleCoppola\PHPeg\Expression\RuleReferenceExpression;
use EmanueleCoppola\PHPeg\Expression\SequenceExpression;
use EmanueleCoppola\PHPeg\Expression\SpanEqualExpression;
use EmanueleCoppola\PHPeg\Expression\SpanNotEqualExpression;
use EmanueleCoppola\PHPeg\Expression\ZeroOrMoreExpression;
use EmanueleCoppola\PHPeg\Expression\AnyCharacterExpression;
use EmanueleCoppola\PHPeg\Expression\CharClassExpression;
use EmanueleCoppola\PHPeg\Expression\EndOfInputExpression;
use EmanueleCoppola\PHPeg\Expression\LiteralExpression;

/**
 * Traverses the PEG expression graph reachable from a grammar.
 */
class GrammarExpressionTraverser extends AbstractExpressionVisitor
{
    /**
     * @var array<int, true>
     */
    private array $visitedExpressions = [];

    /**
     * @var array<string, true>
     */
    private array $visitedRules = [];

    /**
     * Creates a traverser bound to the target expression visitor.
     */
    public function __construct(
        private readonly ExpressionVisitorInterface $visitor,
    ) {
    }

    /**
     * Traverses the expression graph reachable from the grammar.
     */
    public function traverse(Grammar $grammar, ?string $startRule = null, bool $includeLakeProfiles = true): void
    {
        $this->visitedExpressions = [];
        $this->visitedRules = [];

        $roots = $startRule === null
            ? array_values($grammar->rules())
            : [$grammar->rule($startRule) ?? throw new \InvalidArgumentException(sprintf('Unknown start rule "%s".', $startRule))];

        foreach ($roots as $rule) {
            $this->walk($rule->expression(), $grammar, 0);
        }

        if ($includeLakeProfiles) {
            foreach ($grammar->lakeProfiles() as $expression) {
                $this->walk($expression, $grammar, 0);
            }
        }
    }

    /**
     * Visits any expression kind.
     */
    public function visitExpression(ExpressionInterface $expression, int $depth): void
    {
        $this->visitor->visitExpression($expression, $depth);
    }

    /**
     * Visits an any-character expression.
     */
    public function visitAnyCharacter(AnyCharacterExpression $expression, int $depth): void
    {
        $this->visitor->visitAnyCharacter($expression, $depth);
    }

    /**
     * Visits a positive lookahead expression.
     */
    public function visitAndPredicate(AndPredicateExpression $expression, int $depth): void
    {
        $this->visitor->visitAndPredicate($expression, $depth);
    }

    /**
     * Visits an ordered-choice expression.
     */
    public function visitChoice(ChoiceExpression $expression, int $depth): void
    {
        $this->visitor->visitChoice($expression, $depth);
    }

    /**
     * Visits a character class expression.
     */
    public function visitCharClass(CharClassExpression $expression, int $depth): void
    {
        $this->visitor->visitCharClass($expression, $depth);
    }

    /**
     * Visits an end-of-input expression.
     */
    public function visitEndOfInput(EndOfInputExpression $expression, int $depth): void
    {
        $this->visitor->visitEndOfInput($expression, $depth);
    }

    /**
     * Visits a lake expression.
     */
    public function visitLake(LakeExpression $expression, int $depth): void
    {
        $this->visitor->visitLake($expression, $depth);
    }

    /**
     * Visits a literal expression.
     */
    public function visitLiteral(LiteralExpression $expression, int $depth): void
    {
        $this->visitor->visitLiteral($expression, $depth);
    }

    /**
     * Visits a named capture expression.
     */
    public function visitNamedCapture(NamedCaptureExpression $expression, int $depth): void
    {
        $this->visitor->visitNamedCapture($expression, $depth);
    }

    /**
     * Visits a negative lookahead expression.
     */
    public function visitNotPredicate(NotPredicateExpression $expression, int $depth): void
    {
        $this->visitor->visitNotPredicate($expression, $depth);
    }

    /**
     * Visits a one-or-more repetition expression.
     */
    public function visitOneOrMore(OneOrMoreExpression $expression, int $depth): void
    {
        $this->visitor->visitOneOrMore($expression, $depth);
    }

    /**
     * Visits an optional expression.
     */
    public function visitOptional(OptionalExpression $expression, int $depth): void
    {
        $this->visitor->visitOptional($expression, $depth);
    }

    /**
     * Visits a regular expression.
     */
    public function visitRegex(RegexExpression $expression, int $depth): void
    {
        $this->visitor->visitRegex($expression, $depth);
    }

    /**
     * Visits a rule reference expression.
     */
    public function visitRuleReference(RuleReferenceExpression $expression, int $depth): void
    {
        $this->visitor->visitRuleReference($expression, $depth);
    }

    /**
     * Visits a sequence expression.
     */
    public function visitSequence(SequenceExpression $expression, int $depth): void
    {
        $this->visitor->visitSequence($expression, $depth);
    }

    /**
     * Visits an equal-span expression.
     */
    public function visitSpanEqual(SpanEqualExpression $expression, int $depth): void
    {
        $this->visitor->visitSpanEqual($expression, $depth);
    }

    /**
     * Visits a not-equal-span expression.
     */
    public function visitSpanNotEqual(SpanNotEqualExpression $expression, int $depth): void
    {
        $this->visitor->visitSpanNotEqual($expression, $depth);
    }

    /**
     * Visits a zero-or-more repetition expression.
     */
    public function visitZeroOrMore(ZeroOrMoreExpression $expression, int $depth): void
    {
        $this->visitor->visitZeroOrMore($expression, $depth);
    }

    /**
     * @param ExpressionInterface $expression
     */
    private function walk(ExpressionInterface $expression, Grammar $grammar, int $depth): void
    {
        $key = spl_object_id($expression);
        if (isset($this->visitedExpressions[$key])) {
            return;
        }

        $this->visitedExpressions[$key] = true;
        $this->dispatch($expression, $depth);

        match (true) {
            $expression instanceof SequenceExpression => $this->walkSequence($expression, $grammar, $depth),
            $expression instanceof ChoiceExpression => $this->walkChoice($expression, $grammar, $depth),
            $expression instanceof AndPredicateExpression => $this->walk($expression->expression(), $grammar, $depth + 1),
            $expression instanceof NotPredicateExpression => $this->walk($expression->expression(), $grammar, $depth + 1),
            $expression instanceof OptionalExpression => $this->walk($expression->expression(), $grammar, $depth + 1),
            $expression instanceof ZeroOrMoreExpression => $this->walk($expression->expression(), $grammar, $depth + 1),
            $expression instanceof OneOrMoreExpression => $this->walk($expression->expression(), $grammar, $depth + 1),
            $expression instanceof NamedCaptureExpression => $this->walk($expression->expression(), $grammar, $depth + 1),
            $expression instanceof SpanEqualExpression => $this->walkSpanEqual($expression, $grammar, $depth),
            $expression instanceof SpanNotEqualExpression => $this->walkSpanNotEqual($expression, $grammar, $depth),
            $expression instanceof RuleReferenceExpression => $this->walkRuleReference($expression, $grammar, $depth),
            default => null,
        };
    }

    private function dispatch(ExpressionInterface $expression, int $depth): void
    {
        match (true) {
            $expression instanceof AnyCharacterExpression => $this->visitAnyCharacter($expression, $depth),
            $expression instanceof AndPredicateExpression => $this->visitAndPredicate($expression, $depth),
            $expression instanceof ChoiceExpression => $this->visitChoice($expression, $depth),
            $expression instanceof CharClassExpression => $this->visitCharClass($expression, $depth),
            $expression instanceof EndOfInputExpression => $this->visitEndOfInput($expression, $depth),
            $expression instanceof LakeExpression => $this->visitLake($expression, $depth),
            $expression instanceof LiteralExpression => $this->visitLiteral($expression, $depth),
            $expression instanceof NamedCaptureExpression => $this->visitNamedCapture($expression, $depth),
            $expression instanceof NotPredicateExpression => $this->visitNotPredicate($expression, $depth),
            $expression instanceof OneOrMoreExpression => $this->visitOneOrMore($expression, $depth),
            $expression instanceof OptionalExpression => $this->visitOptional($expression, $depth),
            $expression instanceof RegexExpression => $this->visitRegex($expression, $depth),
            $expression instanceof RuleReferenceExpression => $this->visitRuleReference($expression, $depth),
            $expression instanceof SequenceExpression => $this->visitSequence($expression, $depth),
            $expression instanceof SpanEqualExpression => $this->visitSpanEqual($expression, $depth),
            $expression instanceof SpanNotEqualExpression => $this->visitSpanNotEqual($expression, $depth),
            $expression instanceof ZeroOrMoreExpression => $this->visitZeroOrMore($expression, $depth),
            default => $this->visitExpression($expression, $depth),
        };
    }

    private function walkSequence(SequenceExpression $expression, Grammar $grammar, int $depth): void
    {
        foreach ($expression->expressions() as $child) {
            $this->walk($child, $grammar, $depth + 1);
        }
    }

    private function walkChoice(ChoiceExpression $expression, Grammar $grammar, int $depth): void
    {
        foreach ($expression->alternatives() as $child) {
            $this->walk($child, $grammar, $depth + 1);
        }
    }

    private function walkSpanEqual(SpanEqualExpression $expression, Grammar $grammar, int $depth): void
    {
        $this->walk($expression->left(), $grammar, $depth + 1);
        $this->walk($expression->right(), $grammar, $depth + 1);
    }

    private function walkSpanNotEqual(SpanNotEqualExpression $expression, Grammar $grammar, int $depth): void
    {
        $this->walk($expression->left(), $grammar, $depth + 1);
        $this->walk($expression->right(), $grammar, $depth + 1);
    }

    private function walkRuleReference(RuleReferenceExpression $expression, Grammar $grammar, int $depth): void
    {
        if (isset($this->visitedRules[$expression->ruleName()])) {
            return;
        }

        $rule = $grammar->rule($expression->ruleName());
        if ($rule === null) {
            return;
        }

        $this->visitedRules[$expression->ruleName()] = true;
        $this->walk($rule->expression(), $grammar, $depth + 1);
    }
}
