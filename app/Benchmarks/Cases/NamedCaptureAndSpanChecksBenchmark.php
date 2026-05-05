<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\App\Benchmarks\Cases;

use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;
use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Result\ParseResult;

/**
 * Measures parsing on a feature-shaped format that exercises named captures and span equality.
 */
class NamedCaptureAndSpanChecksBenchmark extends AbstractBenchmarkCase
{
    /**
     * @var array<string, Grammar>
     */
    private array $grammars = [];

    /**
     * Returns the human-readable benchmark name.
     */
    public function name(): string
    {
        return 'Named capture and span checks';
    }

    /**
     * Returns the stable benchmark identifier used in filters and history.
     */
    public function slug(): string
    {
        return 'named-capture-and-span-checks';
    }

    /**
     * Builds the grammar for the provided benchmark scale.
     */
    public function grammar(string $scale): Grammar
    {
        if (!isset($this->grammars[$scale])) {
            $builder = GrammarBuilder::create();
            $tagName = $builder->regex('[a-z][a-z0-9-]*');
            $summaryText = $builder->regex('[^<]+');
            $spanCheckCode = $builder->sameSpan(
                $builder->regex('[A-Z]{2}[0-9]{2}'),
                $builder->regex('[A-Z0-9]{4}'),
            );

            $builder
                ->grammar('NamedCaptureAndSpanChecks')
                ->rule('NamedCaptureAndSpanChecks', $builder->seq(
                    $builder->oneOrMore($builder->ref('FeatureSample')),
                    $builder->eof(),
                ))
                ->rule('FeatureSample', $builder->seq(
                    $builder->literal('<'),
                    $builder->capture('feature', $tagName),
                    $builder->literal('>'),
                    $builder->ref('NamedCapture'),
                    $builder->ref('SpanChecks'),
                    $builder->ref('Result'),
                    $builder->literal('</'),
                    $builder->capture('feature', $tagName),
                    $builder->literal('>'),
                ))
                ->rule('NamedCapture', $builder->seq(
                    $builder->literal('<named-capture>'),
                    $summaryText,
                    $builder->literal('</named-capture>'),
                ))
                ->rule('SpanChecks', $builder->seq(
                    $builder->literal('<span-checks>'),
                    $builder->oneOrMore($builder->ref('SpanCheck')),
                    $builder->literal('</span-checks>'),
                ))
                ->rule('SpanCheck', $builder->seq(
                    $builder->literal('<span-check>'),
                    $spanCheckCode,
                    $builder->literal(' '),
                    $summaryText,
                    $builder->literal('</span-check>'),
                ))
                ->rule('Result', $builder->seq(
                    $builder->literal('<result>'),
                    $summaryText,
                    $builder->literal('</result>'),
                ));

            $this->grammars[$scale] = $builder->build();
        }

        return $this->grammars[$scale];
    }

    /**
     * Generates deterministic input for the provided benchmark scale.
     */
    public function input(string $scale): string
    {
        $sampleCount = $this->sizeForScale($scale, [
            'small' => 80,
            'medium' => 240,
            'large' => 640,
        ]);

        $spanChecksPerSample = $this->sizeForScale($scale, [
            'small' => 2,
            'medium' => 3,
            'large' => 4,
        ]);

        return $this->buildInput($sampleCount, $spanChecksPerSample);
    }

    /**
     * Verifies the parse result for the generated input.
     */
    public function validate(ParseResult $result, string $input): void
    {
        $this->assertSuccessfulFullMatch($result, $input);
    }

    /**
     * Returns the grammar source placeholder.
     */
    protected function grammarSource(string $scale): string
    {
        return '';
    }

    /**
     * Returns the benchmark start rule.
     */
    protected function startRule(): string
    {
        return 'NamedCaptureAndSpanChecks';
    }

    /**
     * Builds deterministic feature-shaped markup.
     */
    private function buildInput(int $sampleCount, int $spanChecksPerSample): string
    {
        $output = [];

        for ($sampleIndex = 0; $sampleIndex < $sampleCount; $sampleIndex++) {
            $output[] = '<named-capture-and-span-checks>';
            $output[] = '<named-capture>' . $this->namedCaptureText($sampleIndex) . '</named-capture>';
            $output[] = '<span-checks>';

            for ($spanCheckIndex = 0; $spanCheckIndex < $spanChecksPerSample; $spanCheckIndex++) {
                $output[] = $this->buildSpanCheck($sampleIndex, $spanCheckIndex);
            }

            $output[] = '</span-checks>';
            $output[] = '<result>' . $this->resultText($sampleIndex) . '</result>';
            $output[] = '</named-capture-and-span-checks>';
        }

        return implode('', $output);
    }

    /**
     * Builds one span check with a fixed-width code that exercises span equality.
     */
    private function buildSpanCheck(int $sampleIndex, int $spanCheckIndex): string
    {
        $code = sprintf(
            '%s%s',
            chr(ord('A') + (($sampleIndex + $spanCheckIndex) % 26)),
            chr(ord('A') + (($sampleIndex * 3 + $spanCheckIndex) % 26)),
        ) . sprintf('%02d', ($sampleIndex + $spanCheckIndex) % 100);

        return sprintf(
            '<span-check>%s %s</span-check>',
            $code,
            $this->spanCheckText($sampleIndex, $spanCheckIndex),
        );
    }

    /**
     * Builds the named capture text for one sample.
     */
    private function namedCaptureText(int $sampleIndex): string
    {
        return sprintf(
            'named capture sample %03d validates tag reuse on node %02d',
            $sampleIndex,
            ($sampleIndex % 17) + 1,
        );
    }

    /**
     * Builds the span check body text.
     */
    private function spanCheckText(int $sampleIndex, int $spanCheckIndex): string
    {
        return sprintf(
            'span check %03d-%02d confirms equal end offset',
            $sampleIndex,
            $spanCheckIndex,
        );
    }

    /**
     * Builds the result text for one sample.
     */
    private function resultText(int $sampleIndex): string
    {
        return sprintf(
            'named capture and span checks pass for sample %03d',
            $sampleIndex,
        );
    }
}
