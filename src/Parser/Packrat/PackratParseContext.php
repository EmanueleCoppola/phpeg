<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Parser\Packrat;

use EmanueleCoppola\PHPeg\App\Trace\ParserTraceRecorder;
use EmanueleCoppola\PHPeg\Error\LeftRecursionException;
use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Grammar\Rule;
use EmanueleCoppola\PHPeg\Parser\InputBuffer;
use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Parser\ParserOptions;
use EmanueleCoppola\PHPeg\Parser\RuleMemoEntry;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Parse context for the standard packrat runtime.
 */
class PackratParseContext extends ParseContext
{
    /**
     * @var array<string, array<int, true>>
     */
    private array $evaluatingRules = [];

    /**
     * Initializes a new PackratParseContext instance.
     */
    public function __construct(
        Grammar $grammar,
        InputBuffer $input,
        ParserOptions $options = new ParserOptions(),
        ?ParserTraceRecorder $traceRecorder = null,
    ) {
        parent::__construct($grammar, $input, $options, $traceRecorder);
    }

    /**
     * Matches a named rule with memoization.
     */
    public function matchRule(string $ruleName, int $offset): ?MatchResult
    {
        $rule = $this->rules[$ruleName] ?? null;
        if ($rule === null) {
            $frameId = $this->traceEnter('rule', [
                'id' => 'rule:' . $ruleName,
                'name' => $ruleName,
                'label' => $ruleName,
                'description' => sprintf('rule <%s>', $ruleName),
            ], $offset);
            $this->traceExit($frameId, false, null);
            $this->recordFailure($offset, sprintf('rule <%s>', $ruleName));

            return null;
        }

        $ignoreCase = $this->effectiveRuleIgnoreCase($rule);
        $ruleKey = $this->ruleMemoKey($ruleName, $ignoreCase);

        if (!$this->memoizationEnabled) {
            return $this->matchRuleWithoutMemoization($ruleKey, $rule, $offset);
        }

        $entry = $this->memo[$ruleKey][$offset] ?? null;
        if ($entry instanceof RuleMemoEntry) {
            if ($entry->isEvaluating()) {
                throw new LeftRecursionException($ruleName, $offset);
            }

            return $entry->result();
        }

        $rule = $this->rules[$ruleName] ?? null;
        if ($rule === null) {
            $this->recordFailure($offset, sprintf('rule <%s>', $ruleName));

            return null;
        }

        $entry = new RuleMemoEntry();
        $this->memo[$ruleKey][$offset] = $entry;

        try {
            $entry->beginEvaluation();
            $result = $rule->match($this, $offset);
        } finally {
            $entry->finishEvaluation();
        }

        $entry->setResult($result);

        $this->rememberRuleResult($ruleKey, $offset, $entry);

        return $result;
    }

    /**
     * Matches a rule with only active-call tracking when memoization is disabled.
     */
    private function matchRuleWithoutMemoization(string $ruleKey, Rule $rule, int $offset): ?MatchResult
    {
        if (isset($this->evaluatingRules[$ruleKey][$offset])) {
            throw new LeftRecursionException($rule->name(), $offset);
        }

        $this->evaluatingRules[$ruleKey][$offset] = true;
        try {
            return $rule->match($this, $offset);
        } finally {
            unset($this->evaluatingRules[$ruleKey][$offset]);
            if ($this->evaluatingRules[$ruleKey] === []) {
                unset($this->evaluatingRules[$ruleKey]);
            }
        }
    }
}
