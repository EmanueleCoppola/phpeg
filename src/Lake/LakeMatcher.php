<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Lake;

use EmanueleCoppola\PHPeg\Ast\AstNode;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;
use EmanueleCoppola\PHPeg\Expression\LakeExpression;
use EmanueleCoppola\PHPeg\Parser\ParseContext;
use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Matches lake expressions against the compiled stop plan.
 */
class LakeMatcher
{
    /**
     * Attempts to match a lake expression at the given offset.
     */
    public static function match(ParseContext $context, LakeExpression $lake, int $offset): ?MatchResult
    {
        $input = $context->input();
        $plan = $context->lakePlan();
        $sequences = $plan->stopSequencesFor($lake);
        if ($sequences === []) {
            $context->recordFailure($offset, $lake->describe());

            return null;
        }

        $waterProfile = $lake->name() === null ? null : $context->grammar()->lakeProfile($lake->name());
        $waterRules = $waterProfile === null ? $context->grammar()->waterRules() : [];
        $cursor = $offset;
        $length = $input->length();
        $children = [];

        while ($cursor <= $length) {
            foreach ($sequences as $sequence) {
                if (!$sequence->canStartAt($context, $cursor)) {
                    continue;
                }

                $result = $context->withBannedLakeIds(
                    [spl_object_id($lake) => $cursor],
                    fn () => $sequence->match($context, $cursor),
                );

                if ($result !== null) {
                    return self::buildResult($context, $lake, $offset, $cursor, $children);
                }
            }

            if ($cursor === $length) {
                break;
            }

            if ($waterProfile !== null) {
                $result = self::matchWaterExpression($context, $lake->name() ?? 'Lake', $waterProfile, $cursor);
                if ($result !== null) {
                    array_push($children, ...$result->nodes());
                    $cursor = $result->endOffset();
                    continue;
                }
            }

            foreach ($waterRules as $waterRule) {
                $result = $context->matchRuleSilently($waterRule->name(), $cursor);
                if ($result === null || $result->endOffset() === $cursor) {
                    continue;
                }

                array_push($children, ...$result->nodes());
                $cursor = $result->endOffset();
                continue 2;
            }

            $cursor++;
        }

        foreach ($sequences as $sequence) {
            $expected = $sequence->firstExpression()?->describe() ?? 'EOF';
            $context->recordFailure($length, $expected);
        }

        return null;
    }

    /**
     * Matches a lake-specific water expression and wraps it into a water node.
     */
    private static function matchWaterExpression(ParseContext $context, string $name, ExpressionInterface $expression, int $offset): ?MatchResult
    {
        $result = $context->matchExpressionSilently($expression, $offset);
        if ($result === null || $result->endOffset() === $offset) {
            return null;
        }

        $node = new AstNode(
            $name,
            $context->options()->lazyNodeText() ? null : $context->input()->slice($offset, $result->endOffset()),
            $offset,
            $result->endOffset(),
            $result->nodes(),
            ['kind' => 'water'],
            true,
            null,
            $context->options()->lazyNodeText() ? $context->input() : null,
        );

        return new MatchResult($offset, $result->endOffset(), [$node]);
    }

    /**
     * Builds the AST result for a matched lake.
     *
     * @param list<AstNode> $children
     */
    private static function buildResult(ParseContext $context, LakeExpression $lake, int $startOffset, int $endOffset, array $children): MatchResult
    {
        if (!$lake->capture()) {
            return new MatchResult($startOffset, $endOffset);
        }

        $node = new AstNode(
            $lake->name() ?? 'Lake',
            $context->options()->lazyNodeText() ? null : $context->input()->slice($startOffset, $endOffset),
            $startOffset,
            $endOffset,
            $children,
            ['kind' => 'lake'],
            true,
            null,
            $context->options()->lazyNodeText() ? $context->input() : null,
        );

        return new MatchResult($startOffset, $endOffset, [$node]);
    }
}
