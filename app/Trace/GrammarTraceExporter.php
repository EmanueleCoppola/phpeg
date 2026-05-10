<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\App\Trace;

use EmanueleCoppola\PHPeg\Expression\AndPredicateExpression;
use EmanueleCoppola\PHPeg\Expression\AnyCharacterExpression;
use EmanueleCoppola\PHPeg\Expression\CharClassExpression;
use EmanueleCoppola\PHPeg\Expression\ChoiceExpression;
use EmanueleCoppola\PHPeg\Expression\EndOfInputExpression;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;
use EmanueleCoppola\PHPeg\Expression\LakeExpression;
use EmanueleCoppola\PHPeg\Expression\LiteralExpression;
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
use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Grammar\Rule;

/**
 * Serializes a grammar and its rules into a trace-friendly JSON shape.
 */
class GrammarTraceExporter
{
    /**
     * Builds a serializable grammar snapshot.
     *
     * @return array<string, mixed>
     */
    public function export(Grammar $grammar): array
    {
        $rules = [];
        foreach ($grammar->rules() as $rule) {
            $rules[] = $this->exportRule($rule);
        }

        $lakeProfiles = [];
        foreach ($grammar->lakeProfiles() as $name => $expression) {
            $lakeProfiles[] = [
                'name' => $name,
                'expression' => $this->exportExpression($expression),
            ];
        }

        return [
            'startRule' => $grammar->startRule(),
            'rules' => $rules,
            'lakeProfiles' => $lakeProfiles,
        ];
    }

    /**
     * Serializes a rule node.
     *
     * @return array<string, mixed>
     */
    private function exportRule(Rule $rule): array
    {
        return [
            'id' => $this->nodeId('rule', $rule->name()),
            'kind' => 'rule',
            'name' => $rule->name(),
            'water' => $rule->isWater(),
            'stateful' => $rule->isStateful(),
            'expression' => $this->exportExpression($rule->expression()),
        ];
    }

    /**
     * Serializes an expression tree node.
     *
     * @return array<string, mixed>
     */
    private function exportExpression(ExpressionInterface $expression): array
    {
        $node = [
            'id' => $this->nodeId('expr', $expression),
            'kind' => $this->expressionKind($expression),
            'label' => $expression->describe(),
            'stateful' => $expression->isStateful(),
            'children' => [],
        ];

        if ($expression instanceof LiteralExpression) {
            $node['literal'] = $expression->literal();
        } elseif ($expression instanceof RegexExpression) {
            $node['pattern'] = $expression->pattern();
            $node['canMatchEmpty'] = $expression->canMatchEmpty();
        } elseif ($expression instanceof CharClassExpression) {
            $node['pattern'] = $expression->pattern();
        } elseif ($expression instanceof RuleReferenceExpression) {
            $node['ruleName'] = $expression->ruleName();
        } elseif ($expression instanceof LakeExpression) {
            $node['name'] = $expression->name();
            $node['capture'] = $expression->capture();
        } elseif ($expression instanceof NamedCaptureExpression) {
            $node['name'] = $expression->name();
            $node['children'][] = $this->exportExpression($expression->expression());
        } elseif ($expression instanceof OptionalExpression) {
            $node['children'][] = $this->exportExpression($expression->expression());
        } elseif ($expression instanceof ZeroOrMoreExpression) {
            $node['children'][] = $this->exportExpression($expression->expression());
        } elseif ($expression instanceof OneOrMoreExpression) {
            $node['children'][] = $this->exportExpression($expression->expression());
        } elseif ($expression instanceof AndPredicateExpression) {
            $node['children'][] = $this->exportExpression($expression->expression());
        } elseif ($expression instanceof NotPredicateExpression) {
            $node['children'][] = $this->exportExpression($expression->expression());
        } elseif ($expression instanceof SequenceExpression) {
            foreach ($expression->expressions() as $child) {
                $node['children'][] = $this->exportExpression($child);
            }
        } elseif ($expression instanceof ChoiceExpression) {
            foreach ($expression->alternatives() as $child) {
                $node['children'][] = $this->exportExpression($child);
            }
        } elseif ($expression instanceof SpanEqualExpression) {
            $node['children'][] = $this->exportExpression($expression->left());
            $node['children'][] = $this->exportExpression($expression->right());
        } elseif ($expression instanceof SpanNotEqualExpression) {
            $node['children'][] = $this->exportExpression($expression->left());
            $node['children'][] = $this->exportExpression($expression->right());
        }

        return $node;
    }

    /**
     * Returns the expression kind used by the UI.
     */
    private function expressionKind(ExpressionInterface $expression): string
    {
        return match (true) {
            $expression instanceof LiteralExpression => 'literal',
            $expression instanceof RegexExpression => 'regex',
            $expression instanceof CharClassExpression => 'char-class',
            $expression instanceof AnyCharacterExpression => 'any',
            $expression instanceof EndOfInputExpression => 'eof',
            $expression instanceof RuleReferenceExpression => 'rule-reference',
            $expression instanceof LakeExpression => 'lake',
            $expression instanceof OptionalExpression => 'optional',
            $expression instanceof ZeroOrMoreExpression => 'zero-or-more',
            $expression instanceof OneOrMoreExpression => 'one-or-more',
            $expression instanceof AndPredicateExpression => 'and-predicate',
            $expression instanceof NotPredicateExpression => 'not-predicate',
            $expression instanceof NamedCaptureExpression => 'named-capture',
            $expression instanceof SequenceExpression => 'sequence',
            $expression instanceof ChoiceExpression => 'choice',
            $expression instanceof SpanEqualExpression => 'span-equal',
            $expression instanceof SpanNotEqualExpression => 'span-not-equal',
            default => 'expression',
        };
    }

    /**
     * Builds a stable trace node identifier.
     *
     * @param object|string $value
     */
    private function nodeId(string $prefix, object|string $value): string
    {
        if (is_string($value)) {
            return $prefix . ':' . $value;
        }

        return $prefix . ':' . spl_object_id($value);
    }
}
