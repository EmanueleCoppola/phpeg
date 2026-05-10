<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Grammar;

use RuntimeException;
use EmanueleCoppola\PHPeg\App\Trace\ParserTraceRecorder;
use EmanueleCoppola\PHPeg\Document\ParsedDocument;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;
use EmanueleCoppola\PHPeg\Parser\Parser;
use EmanueleCoppola\PHPeg\Parser\ParserOptions;
use EmanueleCoppola\PHPeg\Result\ParseResult;

/**
 * Immutable PEG grammar container.
 */
class Grammar
{
    /**
     * @param array<string, Rule> $rules
     * @param array<string, ExpressionInterface> $lakeProfiles
     */
    public function __construct(
        private readonly array $rules,
        private readonly string $startRule,
        private readonly array $lakeProfiles = [],
    ) {
        $stateful = false;

        foreach ($rules as $rule) {
            if ($rule->isStateful()) {
                $stateful = true;
                break;
            }
        }

        $this->hasStatefulExpressions = $stateful;
    }

    /**
     * @var ?bool
     */
    private ?bool $leftRecursionRequired = null;

    /**
     * @var bool
     */
    private readonly bool $hasStatefulExpressions;

    /**
     * Returns the configured start rule name.
     */
    public function startRule(): string
    {
        return $this->startRule;
    }

    /**
     * Returns a rule by name, or null when missing.
     */
    public function rule(string $name): ?Rule
    {
        return $this->rules[$name] ?? null;
    }

    /**
     * @return array<string, Rule>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Returns the rules annotated as water, preserving grammar order.
     *
     * @return list<Rule>
     */
    public function waterRules(): array
    {
        return array_values(array_filter(
            $this->rules,
            static fn (Rule $rule): bool => $rule->isWater(),
        ));
    }

    /**
     * Returns the lake profile expression for the provided lake name, or null when missing.
     */
    public function lakeProfile(string $name): ?ExpressionInterface
    {
        return $this->lakeProfiles[$name] ?? null;
    }

    /**
     * Returns all named lake profiles.
     *
     * @return array<string, ExpressionInterface>
     */
    public function lakeProfiles(): array
    {
        return $this->lakeProfiles;
    }

    /**
     * Returns whether the grammar contains binding-aware expressions.
     */
    public function hasStatefulExpressions(): bool
    {
        return $this->hasStatefulExpressions;
    }

    /**
     * Returns whether the grammar requires left-recursive parsing support.
     */
    public function requiresLeftRecursion(): bool
    {
        if ($this->leftRecursionRequired === null) {
            $this->leftRecursionRequired = LeftRecursionAnalyzer::detect($this);
        }

        return $this->leftRecursionRequired;
    }

    /**
     * Parses input using the configured grammar.
     */
    public function parse(string $input, ?string $startRule = null, ?ParserOptions $options = null, ?ParserTraceRecorder $traceRecorder = null): ParseResult
    {
        $parser = new Parser($options ?? new ParserOptions());

        return $parser->parse($this, $input, $startRule, $options, $traceRecorder);
    }

    /**
     * Parses an editable source-preserving document.
     */
    public function parseDocument(string $input, ?string $startRule = null, ?ParserOptions $options = null, ?ParserTraceRecorder $traceRecorder = null): ParsedDocument
    {
        $result = $this->parse($input, $startRule, $options, $traceRecorder);
        if (!$result->isSuccess() || $result->node() === null) {
            throw new RuntimeException($result->error()?->message() ?? 'Unable to parse document.');
        }

        return new ParsedDocument($this, $input, $result->node());
    }

}
