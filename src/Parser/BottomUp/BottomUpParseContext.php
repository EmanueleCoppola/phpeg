<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Parser\BottomUp;

use EmanueleCoppola\PHPeg\App\Trace\ParserTraceRecorder;
use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Grammar\Rule;
use EmanueleCoppola\PHPeg\Parser\InputBuffer;
use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Parser\ParserOptions;
use EmanueleCoppola\PHPeg\Parser\RuleMemoEntry;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Parse context for the left-recursive bottom-up runtime.
 */
class BottomUpParseContext extends ParseContext
{
    /**
     * Tracks whether the parser is rescanning a left-recursive rule.
     *
     * @var int
     */
    private int $leftRecursionRescanningDepth = 0;

    /**
     * Initializes a new BottomUpParseContext instance.
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
     * Matches a named rule with left-recursive growth support.
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

        $entry = $this->memo[$ruleKey][$offset] ?? null;
        if ($entry instanceof RuleMemoEntry) {
            if ($entry->isEvaluating()) {
                $entry->markLeftRecursive();

                return $entry->result();
            }

            if ($this->leftRecursionRescanningDepth === 0) {
                return $entry->result();
            }
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

        if ($entry->hasLeftRecursion() && $result !== null) {
            $result = $this->growLeftRecursiveRule($ruleKey, $rule, $offset, $entry, $result);
            $entry->setResult($result);
        }

        $this->rememberRuleResult($ruleKey, $offset, $entry);

        return $result;
    }

    /**
     * Returns whether the parser is rescanning a left-recursive rule.
     */
    protected function isRescanningLeftRecursiveRule(): bool
    {
        return $this->leftRecursionRescanningDepth > 0;
    }

    /**
     * Resets the caches before rescanning a left-recursive rule.
     */
    private function resetCachesForLeftRecursion(string $ruleKey, int $offset, RuleMemoEntry $entry): void
    {
        $this->memo = [];
        $this->memoOrder = [];
        $this->expressionMemo = [];
        $this->expressionMemoOrder = [];
        $this->memo[$ruleKey][$offset] = $entry;
    }

    /**
     * Re-evaluates a left-recursive rule until the match stops growing.
     */
    private function growLeftRecursiveRule(string $ruleKey, Rule $rule, int $offset, RuleMemoEntry $entry, MatchResult $result): MatchResult
    {
        $bestResult = $result;

        while (true) {
            $this->resetCachesForLeftRecursion($ruleKey, $offset, $entry);
            $entry->setResult($bestResult);
            $entry->beginEvaluation();
            $this->leftRecursionRescanningDepth++;

            try {
                $grownResult = $rule->match($this, $offset);
            } finally {
                $this->leftRecursionRescanningDepth--;
                $entry->finishEvaluation();
            }

            if ($grownResult !== null && $grownResult->endOffset() > $bestResult->endOffset()) {
                $bestResult = $grownResult;
                $entry->setResult($bestResult);

                continue;
            }

            break;
        }

        return $bestResult;
    }
}
