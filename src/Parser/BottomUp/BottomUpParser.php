<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Parser\BottomUp;

use EmanueleCoppola\PHPeg\App\Trace\ParserTraceRecorder;
use EmanueleCoppola\PHPeg\Error\LeftRecursionException;
use EmanueleCoppola\PHPeg\Error\ParseError;
use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Lake\LakeAnalysisException;
use EmanueleCoppola\PHPeg\Parser\InputBuffer;
use EmanueleCoppola\PHPeg\Parser\ParserOptions;
use EmanueleCoppola\PHPeg\Result\MatchResult;
use EmanueleCoppola\PHPeg\Result\ParseResult;

/**
 * Executes the left-recursive bottom-up parser path.
 */
class BottomUpParser
{
    /**
     * Initializes a new BottomUpParser instance.
     */
    public function __construct(
        private readonly ParserOptions $options = new ParserOptions(),
    ) {
    }

    /**
     * Returns the parser options.
     */
    public function options(): ParserOptions
    {
        return $this->options;
    }

    /**
     * Parses input with the provided grammar.
     */
    public function parse(Grammar $grammar, string $input, ?string $startRule = null, ?ParserOptions $options = null, ?ParserTraceRecorder $traceRecorder = null): ParseResult
    {
        $ruleName = $startRule ?? $grammar->startRule();
        $inputBuffer = new InputBuffer($input);

        try {
            $context = new BottomUpParseContext($grammar, $inputBuffer, $options ?? $this->options, $traceRecorder);
            $result = $context->matchRule($ruleName, 0);
        } catch (LakeAnalysisException $exception) {
            return ParseResult::failure(
                0,
                '',
                new ParseError(0, 1, 1, [], '', $exception->getMessage()),
            );
        } catch (LeftRecursionException $exception) {
            $position = $inputBuffer->lineAndColumn($exception->offset());

            return ParseResult::failure(
                $exception->offset(),
                $inputBuffer->slice(0, $exception->offset()),
                ParseError::leftRecursion(
                    $exception->ruleName(),
                    $exception->offset(),
                    $position['line'],
                    $position['column'],
                    $inputBuffer->snippet($exception->offset()),
                ),
            );
        }

        return $this->finalizeParse($context, $inputBuffer, $result);
    }

    /**
     * Converts the low-level match result into a public parse result.
     */
    private function finalizeParse(BottomUpParseContext $context, InputBuffer $inputBuffer, ?MatchResult $result): ParseResult
    {
        if ($result === null || $result->endOffset() !== $inputBuffer->length()) {
            return ParseResult::failure(
                $result?->endOffset() ?? 0,
                $result === null ? '' : $inputBuffer->slice(0, $result->endOffset()),
                $context->error(),
            );
        }

        $nodes = $result->nodes();

        return ParseResult::success($result->endOffset(), $inputBuffer->slice(0, $result->endOffset()), $nodes[0]);
    }
}
