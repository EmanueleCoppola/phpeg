<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Feature;

use EmanueleCoppola\PHPeg\App\Trace\TraceConsoleRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the CLI trace and step commands.
 */
class TraceCommandTest extends TestCase
{
    /**
     * Verifies the trace command exports a JSON document with grammar and steps.
     */
    public function testExportsParserTraceJson(): void
    {
        [$grammarPath, $inputPath, $outputPath] = $this->createTraceFixture();

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'trace',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--output=' . $outputPath,
        ]);

        self::assertSame(0, $exitCode, $stderr);
        self::assertSame('', $stdout);
        self::assertFileExists($outputPath);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('phpeg.trace.v1', $payload['schema']);
        self::assertSame('cleanpeg', $payload['grammar']['format']);
        self::assertSame('Start', $payload['grammar']['startRule']);
        self::assertNotEmpty($payload['grammar']['snapshot']['rules']);
        self::assertNotEmpty($payload['steps']);
        self::assertSame('rule:Start', $payload['steps'][0]['target']['id']);
        self::assertSame('enter', $payload['steps'][0]['phase']);

        @unlink($grammarPath);
        @unlink($inputPath);
        @unlink($outputPath);
    }

    /**
     * Verifies the step command renders a trace snapshot in non-interactive mode.
     */
    public function testRendersATraceSnapshot(): void
    {
        [$grammarPath, $inputPath, $outputPath] = $this->createTraceFixture();

        [$traceExitCode] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'trace',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--output=' . $outputPath,
        ]);
        self::assertSame(0, $traceExitCode);

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'step',
            '--trace=' . $outputPath,
            '--step=0',
        ]);

        self::assertSame(0, $exitCode, $stderr);
        self::assertStringContainsString('PHPeg trace viewer', $stdout);
        self::assertStringContainsString('Grammar', $stdout);
        self::assertStringContainsString('> Start = "a"+ EOF', $stdout);
        self::assertStringContainsString('- Other = "b"', $stdout);
        self::assertStringContainsString('mode=all', $stdout);
        self::assertStringContainsString('Input', $stdout);
        self::assertStringContainsString('Legend', $stdout);
        self::assertStringContainsString('consumed input', $stdout);
        self::assertStringContainsString('current input', $stdout);
        self::assertStringContainsString('match failure', $stdout);
        self::assertStringContainsString('Commands:', $stdout);

        @unlink($grammarPath);
        @unlink($inputPath);
        @unlink($outputPath);
    }

    /**
     * Verifies the step command can filter to matched steps only.
     */
    public function testRendersMatchedOnlyMode(): void
    {
        [$grammarPath, $inputPath, $outputPath] = $this->createTraceFixture();

        [$traceExitCode] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'trace',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--output=' . $outputPath,
        ]);
        self::assertSame(0, $traceExitCode);

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'step',
            '--trace=' . $outputPath,
            '--step=0',
            '--mode=matched',
        ]);

        self::assertSame(0, $exitCode, $stderr);
        self::assertStringContainsString('mode=matched', $stdout);
        self::assertStringContainsString('status=match', $stdout);

        @unlink($grammarPath);
        @unlink($inputPath);
        @unlink($outputPath);
    }

    /**
     * Verifies the step command can filter to failure steps only.
     */
    public function testRendersFailuresOnlyMode(): void
    {
        [$grammarPath, $inputPath, $outputPath] = $this->createTraceFixture();

        [$traceExitCode] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'trace',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--output=' . $outputPath,
        ]);
        self::assertSame(0, $traceExitCode);

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'step',
            '--trace=' . $outputPath,
            '--step=0',
            '--mode=failures',
        ]);

        self::assertSame(0, $exitCode, $stderr);
        self::assertStringContainsString('mode=failures', $stdout);
        self::assertStringContainsString('status=fail', $stdout);

        @unlink($grammarPath);
        @unlink($inputPath);
        @unlink($outputPath);
    }

    /**
     * Verifies matched mode keeps only the winning branch and drops backtracked successes.
     */
    public function testMatchedModeDropsBacktrackedSuccessfulBranches(): void
    {
        $command = new \EmanueleCoppola\PHPeg\App\Commands\StepCommand();
        $reflection = new \ReflectionMethod($command, 'visibleSteps');

        $steps = [
            [
                'phase' => 'enter',
                'frameId' => 1,
                'parentFrameId' => null,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\SequenceExpression', 'label' => 'sequence'],
            ],
            [
                'phase' => 'enter',
                'frameId' => 2,
                'parentFrameId' => 1,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\SequenceExpression', 'label' => 'sequence'],
            ],
            [
                'phase' => 'enter',
                'frameId' => 3,
                'parentFrameId' => 2,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\LiteralExpression', 'label' => '"a"'],
            ],
            [
                'phase' => 'exit',
                'frameId' => 3,
                'parentFrameId' => 2,
                'success' => true,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\LiteralExpression', 'label' => '"a"'],
            ],
            [
                'phase' => 'exit',
                'frameId' => 2,
                'parentFrameId' => 1,
                'success' => false,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\SequenceExpression', 'label' => 'sequence'],
            ],
            [
                'phase' => 'enter',
                'frameId' => 4,
                'parentFrameId' => 1,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\SequenceExpression', 'label' => 'sequence'],
            ],
            [
                'phase' => 'enter',
                'frameId' => 5,
                'parentFrameId' => 4,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\LiteralExpression', 'label' => '"b"'],
            ],
            [
                'phase' => 'enter',
                'frameId' => 6,
                'parentFrameId' => 4,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\RegexExpression', 'label' => 'regex([ \\t\\r\\n]*)'],
            ],
            [
                'phase' => 'exit',
                'frameId' => 6,
                'parentFrameId' => 4,
                'success' => true,
                'offset' => 1,
                'endOffset' => 1,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\RegexExpression', 'label' => 'regex([ \\t\\r\\n]*)'],
            ],
            [
                'phase' => 'exit',
                'frameId' => 5,
                'parentFrameId' => 4,
                'success' => true,
                'offset' => 1,
                'endOffset' => 2,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\LiteralExpression', 'label' => '"b"'],
            ],
            [
                'phase' => 'exit',
                'frameId' => 4,
                'parentFrameId' => 1,
                'success' => true,
                'offset' => 1,
                'endOffset' => 2,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\SequenceExpression', 'label' => 'sequence'],
            ],
            [
                'phase' => 'exit',
                'frameId' => 1,
                'parentFrameId' => null,
                'success' => true,
                'offset' => 0,
                'endOffset' => 2,
                'target' => ['kind' => 'EmanueleCoppola\\PHPeg\\Expression\\SequenceExpression', 'label' => 'sequence'],
            ],
        ];

        /** @var list<array<string, mixed>> $visibleSteps */
        $visibleSteps = $reflection->invoke($command, $steps, 'matched');

        self::assertSame([5], array_values(array_map(
            static fn (array $step): int => (int) $step['frameId'],
            $visibleSteps,
        )));
    }

    /**
     * Verifies recursive active paths keep repeated rule names visible.
     */
    public function testRendersRecursiveActivePathWithRepeatedRules(): void
    {
        $grammarPath = $this->projectRoot() . '/examples/tiny-markup-parser/tiny-markup.cleanpeg';
        $inputPath = $this->projectRoot() . '/examples/tiny-markup-parser/tiny-markup.tm';
        $outputPath = tempnam(sys_get_temp_dir(), 'phpeg-output-');

        if ($outputPath === false) {
            self::fail('Unable to create a temporary trace output file.');
        }

        unlink($outputPath);

        [$traceExitCode] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'trace',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--output=' . $outputPath,
        ]);
        self::assertSame(0, $traceExitCode);

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'step',
            '--trace=' . $outputPath,
            '--step=215',
        ]);

        self::assertSame(0, $exitCode, $stderr);
        self::assertStringContainsString('active path: Document > Element > Content > Element > CloseTagName', $stdout);

        @unlink($outputPath);
    }

    /**
     * Verifies repeated grammar tokens only highlight the focused occurrence.
     */
    public function testHighlightsOnlyTheFocusedRepeatedTokenOccurrence(): void
    {
        $grammarPath = $this->projectRoot() . '/examples/tiny-markup-parser/tiny-markup.cleanpeg';
        $inputPath = $this->projectRoot() . '/examples/tiny-markup-parser/tiny-markup.tm';
        $outputPath = tempnam(sys_get_temp_dir(), 'phpeg-output-');

        if ($outputPath === false) {
            self::fail('Unable to create a temporary trace output file.');
        }

        unlink($outputPath);

        [$traceExitCode] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'trace',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--output=' . $outputPath,
        ]);
        self::assertSame(0, $traceExitCode);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);

        $renderer = new TraceConsoleRenderer();
        $rendered = $renderer->render($payload, 235, true, 'source', 'all');

        self::assertSame(1, substr_count($rendered, "\033[1;43;30m\">\"\033[0m"));

        $elementLine = null;
        foreach (preg_split("/\\R/", $rendered) ?: [] as $line) {
            $plainLine = preg_replace('/\\e\\[[0-9;]*m/', '', $line) ?? $line;
            if (str_contains($plainLine, 'Element =')) {
                $elementLine = $line;
                break;
            }
        }

        self::assertNotNull($elementLine);
        self::assertStringContainsString('"<" tag@OpenTagName ">"', $elementLine);
        self::assertStringContainsString('</" tag@CloseTagName ' . "\033[1;43;30m\">\"\033[0m", $elementLine);
        self::assertStringNotContainsString('"<" tag@OpenTagName ' . "\033[1;43;30m\">\"\033[0m", $elementLine);

        @unlink($outputPath);
    }

    /**
     * Verifies active grammar lines keep their original alignment spacing.
     */
    public function testPreservesGrammarAlignmentSpacingWhenActive(): void
    {
        $renderer = new TraceConsoleRenderer();
        $document = [
            'grammar' => [
                'source' => "Identifier     <- [a-zA-Z_] [a-zA-Z0-9_]*\nOther <- 'x'",
                'snapshot' => [
                    'startRule' => 'Identifier',
                    'rules' => [
                        [
                            'id' => 'rule:Identifier',
                            'kind' => 'rule',
                            'name' => 'Identifier',
                            'water' => false,
                            'stateful' => false,
                            'expression' => [
                                'id' => 'expr:identifier',
                                'kind' => 'expression',
                                'label' => 'identifier',
                                'children' => [],
                            ],
                        ],
                    ],
                    'lakeProfiles' => [],
                ],
            ],
            'input' => [
                'source' => '',
            ],
            'steps' => [
                [
                    'phase' => 'enter',
                    'scope' => 'rule',
                    'target' => [
                        'kind' => 'rule',
                        'name' => 'Identifier',
                        'label' => 'Identifier',
                    ],
                    'path' => ['rule:Identifier'],
                    'offset' => 0,
                    'endOffset' => null,
                    'success' => null,
                    'silent' => false,
                ],
            ],
        ];

        $rendered = $renderer->render($document, 0, false, 'source', 'all');

        self::assertStringContainsString('> Identifier     <- [a-zA-Z_] [a-zA-Z0-9_]*', $rendered);
        self::assertStringContainsString('- Other <- \'x\'', $rendered);
    }

    /**
     * Verifies the tree grammar view remains available through a flag.
     */
    public function testRendersTreeViewWhenRequested(): void
    {
        [$grammarPath, $inputPath, $outputPath] = $this->createTraceFixture();

        [$traceExitCode] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'trace',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--output=' . $outputPath,
        ]);
        self::assertSame(0, $traceExitCode);

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'step',
            '--trace=' . $outputPath,
            '--step=0',
            '--grammar-view=tree',
        ]);

        self::assertSame(0, $exitCode, $stderr);
        self::assertStringContainsString('Start [rule]', $stdout);
        self::assertStringNotContainsString('Start = "a"+ EOF', $stdout);

        @unlink($grammarPath);
        @unlink($inputPath);
        @unlink($outputPath);
    }

    /**
     * Builds a small grammar/input/output fixture.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function createTraceFixture(): array
    {
        $grammarPath = tempnam(sys_get_temp_dir(), 'phpeg-grammar-');
        $inputPath = tempnam(sys_get_temp_dir(), 'phpeg-input-');
        $outputPath = tempnam(sys_get_temp_dir(), 'phpeg-output-');

        if ($grammarPath === false || $inputPath === false || $outputPath === false) {
            self::fail('Unable to create temporary fixture files.');
        }

        unlink($outputPath);

        file_put_contents($grammarPath, <<<CLEANPEG
Start = "a"+ EOF
Other = "b"
CLEANPEG);
        file_put_contents($inputPath, 'aa');

        return [$grammarPath, $inputPath, $outputPath];
    }

    /**
     * Runs a command and captures stdout, stderr, and the exit status.
     *
     * @param list<string> $command
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCommand(array $command): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($this->buildCommandLine($command), $descriptorSpec, $pipes, $this->projectRoot());
        if (!is_resource($process)) {
            self::fail('Unable to start the CLI process.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, $stdout === false ? '' : $stdout, $stderr === false ? '' : $stderr];
    }

    /**
     * Builds a shell-safe command line string.
     *
     * @param list<string> $command
     */
    private function buildCommandLine(array $command): string
    {
        return implode(' ', array_map(
            static fn (string $part): string => escapeshellarg($part),
            $command,
        ));
    }

    /**
     * Returns the project root directory.
     */
    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
