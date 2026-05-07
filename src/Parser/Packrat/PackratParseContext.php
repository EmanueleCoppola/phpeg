<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Parser\Packrat;

use EmanueleCoppola\PHPeg\Error\LeftRecursionException;
use EmanueleCoppola\PHPeg\Grammar\Grammar;
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
    ) {
        parent::__construct($grammar, $input, $options);
    }

    /**
     * Matches a named rule with memoization.
     */
    public function matchRule(string $ruleName, int $offset): ?MatchResult
    {
        if (!$this->memoizationEnabled) {
            return $this->matchRuleWithoutMemoization($ruleName, $offset);
        }

        $entry = $this->memo[$ruleName][$offset] ?? null;
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
        $this->memo[$ruleName][$offset] = $entry;

        try {
            $entry->beginEvaluation();
            $result = $rule->match($this, $offset);
        } finally {
            $entry->finishEvaluation();
        }

        $entry->setResult($result);

        $this->rememberRuleResult($ruleName, $offset, $entry);

        return $result;
    }

    /**
     * Matches a rule with only active-call tracking when memoization is disabled.
     */
    private function matchRuleWithoutMemoization(string $ruleName, int $offset): ?MatchResult
    {
        $rule = $this->rules[$ruleName] ?? null;
        if ($rule === null) {
            $this->recordFailure($offset, sprintf('rule <%s>', $ruleName));

            return null;
        }

        if (isset($this->evaluatingRules[$ruleName][$offset])) {
            throw new LeftRecursionException($ruleName, $offset);
        }

        $this->evaluatingRules[$ruleName][$offset] = true;
        try {
            return $rule->match($this, $offset);
        } finally {
            unset($this->evaluatingRules[$ruleName][$offset]);
            if ($this->evaluatingRules[$ruleName] === []) {
                unset($this->evaluatingRules[$ruleName]);
            }
        }
    }
}
