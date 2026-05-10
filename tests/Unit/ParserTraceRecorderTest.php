<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Unit;

use EmanueleCoppola\PHPeg\App\Trace\ParserTraceRecorder;
use EmanueleCoppola\PHPeg\Result\MatchResult;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the parser trace recorder collects steps and path metadata.
 */
class ParserTraceRecorderTest extends TestCase
{
    /**
     * Verifies trace frames are recorded and the active path is tracked.
     */
    public function testRecordsStepsAndPath(): void
    {
        $recorder = new ParserTraceRecorder();

        $ruleFrameId = $recorder->enter('rule', [
            'id' => 'rule:Start',
            'name' => 'Start',
            'label' => 'Start',
        ], 0, false);

        self::assertSame([1], $recorder->path());

        $expressionFrameId = $recorder->enter('expression', [
            'id' => 'expr:1',
            'label' => 'literal "a"',
        ], 0, true);

        self::assertSame([1, 2], $recorder->path());

        $expressionResult = new MatchResult(0, 1);
        $recorder->exit($expressionFrameId, true, $expressionResult);

        self::assertSame([1], $recorder->path());

        $ruleResult = new MatchResult(0, 1);
        $recorder->exit($ruleFrameId, true, $ruleResult);

        self::assertSame([], $recorder->path());
        self::assertCount(4, $recorder->steps());
        self::assertSame('enter', $recorder->steps()[0]['phase']);
        self::assertSame('enter', $recorder->steps()[1]['phase']);
        self::assertSame('exit', $recorder->steps()[2]['phase']);
        self::assertSame('exit', $recorder->steps()[3]['phase']);
        self::assertSame(['rule:Start'], $recorder->steps()[0]['path']);
        self::assertSame(['rule:Start', 'expr:1'], $recorder->steps()[1]['path']);
        self::assertSame(['rule:Start', 'expr:1'], $recorder->steps()[2]['path']);
        self::assertSame(['rule:Start'], $recorder->steps()[3]['path']);
        self::assertSame(0, $recorder->steps()[0]['offset']);
        self::assertSame(1, $recorder->steps()[2]['endOffset']);
        self::assertSame(1, $recorder->steps()[3]['endOffset']);
    }
}
