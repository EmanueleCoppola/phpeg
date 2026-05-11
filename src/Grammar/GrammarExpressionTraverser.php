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

    public function __construct(
        private readonly ExpressionVisitorInterface $visitor,
    ) {
    }

    /**
     * Traverses the expression graph reachable from the grammar.
     */
    public function traverse(Grammar $grammar, ?string $startRule = null, bool $includeLakeProfiles = true): void
    {
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

    public function visitExpression(ExpressionInterface $expression, int $depth): void
    {
        $this->visitor->visitExpression($expression, $depth);
    }

    public function visitAnyCharacter(AnyCharacterExpression $expression, int $depth): void
    {
        $this->visitor->visitAnyCharacter($expression, $depth);
    }

    public function visitAndPredicate(AndPredicateExpression $expression, int $depth): void
    {
        $this->visitor->visitAndPredicate($expression, $depth);
    }

    public function visitChoice(ChoiceExpression $expression, int $depth): void
    {
        $this->visitor->visitChoice($expression, $depth);
    }

    public function visitCharClass(CharClassExpression $expression, int $depth): void
    {
        $this->visitor->visitCharClass($expression, $depth);
    }

    public function visitEndOfInput(EndOfInputExpression $expression, int $depth): void
    {
        $this->visitor->visitEndOfInput($expression, $depth);
    }

    public function visitLake(LakeExpression $expression, int $depth): void
    {
        $this->visitor->visitLake($expression, $depth);
    }

    public function visitLiteral(LiteralExpression $expression, int $depth): void
    {
        $this->visitor->visitLiteral($expression, $depth);
    }

    public function visitNamedCapture(NamedCaptureExpression $expression, int $depth): void
    {
        $this->visitor->visitNamedCapture($expression, $depth);
    }

    public function visitNotPredicate(NotPredicateExpression $expression, int $depth): void
    {
        $this->visitor->visitNotPredicate($expression, $depth);
    }

    public function visitOneOrMore(OneOrMoreExpression $expression, int $depth): void
    {
        $this->visitor->visitOneOrMore($expression, $depth);
    }

    public function visitOptional(OptionalExpression $expression, int $depth): void
    {
        $this->visitor->visitOptional($expression, $depth);
    }

    public function visitRegex(RegexExpression $expression, int $depth): void
    {
        $this->visitor->visitRegex($expression, $depth);
    }

    public function visitRuleReference(RuleReferenceExpression $expression, int $depth): void
    {
        $this->visitor->visitRuleReference($expression, $depth);
    }

    public function visitSequence(SequenceExpression $expression, int $depth): void
    {
        $this->visitor->visitSequence($expression, $depth);
    }

    public function visitSpanEqual(SpanEqualExpression $expression, int $depth): void
    {
        $this->visitor->visitSpanEqual($expression, $depth);
    }

    public function visitSpanNotEqual(SpanNotEqualExpression $expression, int $depth): void
    {
        $this->visitor->visitSpanNotEqual($expression, $depth);
    }

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
        $expression->accept($this, $depth);

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
