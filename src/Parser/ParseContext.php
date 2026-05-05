<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Parser;

use EmanueleCoppola\PHPeg\Error\ParseError;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;
use EmanueleCoppola\PHPeg\Expression\LakeExpression;
use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Grammar\Rule;
use EmanueleCoppola\PHPeg\Lake\LakeMatcher;
use EmanueleCoppola\PHPeg\Lake\LakePlan;
use EmanueleCoppola\PHPeg\Lake\LakePlanCache;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Holds parser state, memoization, and failure diagnostics shared by parser runtimes.
 */
abstract class ParseContext
{
    /**
     * @var array<string, array<int, RuleMemoEntry>>
     */
    protected array $memo = [];

    /**
     * @var list<array{rule:string,offset:int}>
     */
    protected array $memoOrder = [];

    /**
     * @var int
     */
    protected int $furthestOffset = 0;

    /**
     * @var array<string, true>
     */
    protected array $expected = [];

    /**
     * @var ?string
     */
    protected ?string $optimizedExpected = null;

    /**
     * @var array<int, MatchResult>
     */
    protected array $emptyMatches = [];

    /**
     * @var array<string, MatchResult|null>
     */
    protected array $expressionMemo = [];

    /**
     * @var list<string>
     */
    protected array $expressionMemoOrder = [];

    /**
     * @var int
     */
    protected int $failureSuppressionDepth = 0;

    /**
     * @var list<array<int, int>>
     */
    protected array $bannedLakeIdStack = [];

    /**
     * @var bool
     */
    protected readonly bool $memoizationEnabled;

    /**
     * @var bool
     */
    protected readonly bool $optimizeErrors;

    /**
     * @var bool
     */
    protected readonly bool $reuseEmptyMatches;

    /**
     * @var ?int
     */
    protected readonly ?int $maxCacheEntries;

    /**
     * @var LakePlan
     */
    protected readonly LakePlan $lakePlan;

    /**
     * @var bool
     */
    protected readonly bool $grammarHasStatefulExpressions;

    /**
     * @var list<array<string, string>>
     */
    protected array $bindingFrames = [];

    /**
     * Initializes a new ParseContext instance.
     */
    public function __construct(
        protected readonly Grammar $grammar,
        protected readonly InputBuffer $input,
        protected readonly ParserOptions $options = new ParserOptions(),
    ) {
        $this->memoizationEnabled = $options->memoizationEnabled();
        $this->optimizeErrors = $options->optimizeErrors();
        $this->reuseEmptyMatches = $options->reuseEmptyMatches();
        $this->maxCacheEntries = $options->maxCacheEntries();
        $this->lakePlan = LakePlanCache::forGrammar($grammar);
        $this->grammarHasStatefulExpressions = $grammar->hasStatefulExpressions();
    }

    /**
     * Returns the active grammar.
     */
    public function grammar(): Grammar
    {
        return $this->grammar;
    }

    /**
     * Returns the input buffer.
     */
    public function input(): InputBuffer
    {
        return $this->input;
    }

    /**
     * Returns the active parser options.
     */
    public function options(): ParserOptions
    {
        return $this->options;
    }

    /**
     * Returns the compiled lake plan for this grammar.
     */
    public function lakePlan(): LakePlan
    {
        return $this->lakePlan;
    }

    /**
     * Matches an arbitrary expression with memoization.
     */
    public function matchExpression(ExpressionInterface $expression, int $offset): ?MatchResult
    {
        return $this->matchExpressionInternal($expression, $offset);
    }

    /**
     * Matches an arbitrary expression without recording failures.
     */
    public function matchExpressionSilently(ExpressionInterface $expression, int $offset): ?MatchResult
    {
        $snapshot = $this->grammarHasStatefulExpressions ? $this->snapshotBindings() : null;
        $this->failureSuppressionDepth++;
        try {
            return $this->matchExpressionInternal($expression, $offset);
        } finally {
            $this->failureSuppressionDepth--;
            if ($snapshot !== null) {
                $this->restoreBindings($snapshot);
            }
        }
    }

    /**
     * Matches a lake expression using the compiled lake plan.
     */
    public function matchLakeExpression(LakeExpression $lake, int $offset): ?MatchResult
    {
        return LakeMatcher::match($this, $lake, $offset);
    }

    /**
     * Runs a callback with one or more lake ids temporarily banned.
     *
     * @param array<int, int> $bannedLakeIds
     */
    public function withBannedLakeIds(array $bannedLakeIds, callable $callback): mixed
    {
        $this->bannedLakeIdStack[] = $bannedLakeIds;
        try {
            return $callback();
        } finally {
            array_pop($this->bannedLakeIdStack);
        }
    }

    /**
     * Matches a named rule with memoization.
     */
    abstract public function matchRule(string $ruleName, int $offset): ?MatchResult;

    /**
     * Matches a named rule without recording failures.
     */
    public function matchRuleSilently(string $ruleName, int $offset): ?MatchResult
    {
        $snapshot = $this->grammarHasStatefulExpressions ? $this->snapshotBindings() : null;
        $this->failureSuppressionDepth++;
        try {
            return $this->matchRule($ruleName, $offset);
        } finally {
            $this->failureSuppressionDepth--;
            if ($snapshot !== null) {
                $this->restoreBindings($snapshot);
            }
        }
    }

    /**
     * Pushes a new binding frame on the stack.
     */
    public function pushBindingFrame(): void
    {
        $this->bindingFrames[] = [];
    }

    /**
     * Pops the most recent binding frame.
     */
    public function popBindingFrame(): void
    {
        array_pop($this->bindingFrames);
    }

    /**
     * Returns a snapshot of the current binding stack.
     *
     * @return list<array<string, string>>
     */
    public function snapshotBindings(): array
    {
        return $this->bindingFrames;
    }

    /**
     * Restores a previously captured binding stack snapshot.
     *
     * @param list<array<string, string>> $snapshot
     */
    public function restoreBindings(array $snapshot): void
    {
        $this->bindingFrames = $snapshot;
    }

    /**
     * Returns whether the current binding stack contains the provided name.
     */
    public function hasBinding(string $name): bool
    {
        if ($this->bindingFrames === []) {
            return false;
        }

        $frameIndex = array_key_last($this->bindingFrames);

        return array_key_exists($name, $this->bindingFrames[$frameIndex]);
    }

    /**
     * Returns the binding value for the provided name, if present.
     */
    public function binding(string $name): ?string
    {
        if ($this->bindingFrames === []) {
            return null;
        }

        $frameIndex = array_key_last($this->bindingFrames);
        if (!array_key_exists($name, $this->bindingFrames[$frameIndex])) {
            return null;
        }

        return $this->bindingFrames[$frameIndex][$name];
    }

    /**
     * Stores a binding in the current frame.
     */
    public function setBinding(string $name, string $value): void
    {
        if ($this->bindingFrames === []) {
            $this->pushBindingFrame();
        }

        $frameIndex = array_key_last($this->bindingFrames);
        $this->bindingFrames[$frameIndex][$name] = $value;
    }

    /**
     * Records an expected token description at a failing offset.
     */
    public function recordFailure(int $offset, string $expected): void
    {
        if ($this->failureSuppressionDepth > 0) {
            return;
        }

        if ($offset > $this->furthestOffset) {
            $this->furthestOffset = $offset;
            if ($this->optimizeErrors) {
                $this->optimizedExpected = $expected;
                $this->expected = [];

                return;
            }

            $this->expected = [$expected => true];

            return;
        }

        if ($offset === $this->furthestOffset && $this->optimizeErrors) {
            $this->optimizedExpected ??= $expected;

            return;
        }

        if ($offset === $this->furthestOffset) {
            $this->expected[$expected] = true;
        }
    }

    /**
     * Returns a cached zero-width match at the given offset when enabled.
     */
    public function emptyMatch(int $offset): MatchResult
    {
        if (!$this->reuseEmptyMatches) {
            return MatchResult::empty($offset);
        }

        if (!isset($this->emptyMatches[$offset])) {
            $this->emptyMatches[$offset] = MatchResult::empty($offset);
        }

        return $this->emptyMatches[$offset];
    }

    /**
     * Builds the final parse error.
     */
    public function error(): ParseError
    {
        $position = $this->input->lineAndColumn($this->furthestOffset);
        $expected = $this->optimizeErrors
            ? ($this->optimizedExpected === null ? [] : [$this->optimizedExpected])
            : array_keys($this->expected);

        return new ParseError(
            $this->furthestOffset,
            $position['line'],
            $position['column'],
            $expected,
            $this->input->snippet($this->furthestOffset),
        );
    }

    /**
     * Stores a memoized rule entry and applies the configured cache limit.
     */
    /**
     * Stores a memoized rule entry and applies the configured cache limit.
     */
    protected function rememberRuleResult(string $ruleName, int $offset, RuleMemoEntry $entry): void
    {
        $this->memo[$ruleName][$offset] = $entry;

        if ($this->maxCacheEntries === null) {
            return;
        }

        $this->memoOrder[] = ['rule' => $ruleName, 'offset' => $offset];

        while (count($this->memoOrder) > $this->maxCacheEntries) {
            $entry = array_shift($this->memoOrder);
            if ($entry === null) {
                return;
            }

            unset($this->memo[$entry['rule']][$entry['offset']]);
            if ($this->memo[$entry['rule']] === []) {
                unset($this->memo[$entry['rule']]);
            }
        }
    }

    /**
     * Applies the configured expression memoization limit.
     */
    protected function trimExpressionMemo(): void
    {
        if ($this->maxCacheEntries === null) {
            return;
        }

        while (count($this->expressionMemoOrder) > $this->maxCacheEntries) {
            $key = array_shift($this->expressionMemoOrder);
            if ($key === null) {
                return;
            }

            unset($this->expressionMemo[$key]);
        }
    }

    /**
     * Matches an expression with memoization and lake-ban awareness.
     */
    protected function matchExpressionInternal(ExpressionInterface $expression, int $offset): ?MatchResult
    {
        $cacheKey = null;
        $shouldCache = $this->memoizationEnabled
            && !$this->isRescanningLeftRecursiveRule()
            && (!$this->grammarHasStatefulExpressions || !$this->isStatefulExpression($expression));
        if ($shouldCache) {
            $cacheKey = $this->expressionMemoKey($expression, $offset);
            if (array_key_exists($cacheKey, $this->expressionMemo)) {
                return $this->expressionMemo[$cacheKey];
            }
        }

        if ($expression instanceof LakeExpression && $this->isLakeBanned($expression, $offset)) {
            if ($this->memoizationEnabled && $cacheKey !== null) {
                $this->expressionMemo[$cacheKey] = null;
                $this->expressionMemoOrder[] = $cacheKey;
                $this->trimExpressionMemo();
            }

            return null;
        }

        if ($this->isRescanningLeftRecursiveRule()) {
            return $this->matchExpressionDirect($expression, $offset);
        }

        if (!$shouldCache) {
            return $this->matchExpressionDirect($expression, $offset);
        }

        $result = $this->matchExpressionDirect($expression, $offset);
        $this->expressionMemo[$cacheKey] = $result;
        $this->expressionMemoOrder[] = $cacheKey;
        $this->trimExpressionMemo();

        return $result;
    }

    /**
     * Matches an expression without consulting the memoized cache.
     */
    protected function matchExpressionDirect(ExpressionInterface $expression, int $offset): ?MatchResult
    {
        return $expression->match($this, $offset);
    }

    /**
     * Returns whether the expression depends on parser binding state.
     */
    protected function isStatefulExpression(ExpressionInterface $expression): bool
    {
        return $expression->isStateful();
    }

    /**
     * Returns whether the current stop-match context bans the provided lake.
     */
    protected function isLakeBanned(LakeExpression $lake, int $offset): bool
    {
        $lakeId = spl_object_id($lake);

        for ($index = count($this->bannedLakeIdStack) - 1; $index >= 0; $index--) {
            if (($this->bannedLakeIdStack[$index][$lakeId] ?? null) === $offset) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds a memoization key that includes the active lake-ban signature.
     */
    protected function expressionMemoKey(ExpressionInterface $expression, int $offset): string
    {
        return $this->bannedLakeSignature() . '|' . spl_object_id($expression) . '|' . $offset;
    }

    /**
     * Returns a stable signature for the currently banned lake ids.
     */
    protected function bannedLakeSignature(): string
    {
        if ($this->bannedLakeIdStack === []) {
            return '0';
        }

        $ids = [];
        foreach ($this->bannedLakeIdStack as $frame) {
            foreach ($frame as $lakeId => $originOffset) {
                $ids[$lakeId . '@' . $originOffset] = true;
            }
        }

        $keys = array_map('strval', array_keys($ids));
        sort($keys);

        return implode(',', $keys);
    }

    /**
     * Returns whether the parser is rescanning a left-recursive rule.
     */
    protected function isRescanningLeftRecursiveRule(): bool
    {
        return false;
    }
}
