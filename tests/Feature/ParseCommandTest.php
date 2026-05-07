<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the CLI parse command exports JSON for queried nodes.
 */
class ParseCommandTest extends TestCase
{
    /**
     * @return void
     */
    public function testParsesGrammarAndExportsQueriedNodesAsJson(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'parse',
            '--grammar=' . $this->projectRoot() . '/examples/nginx-config-edit/nginx-config-grammar.cleanpeg',
            '--input=' . $this->projectRoot() . '/examples/nginx-config-edit/nginx-config.conf',
            '--grammar-format=cleanpeg',
            '--query=Block[name="server"]',
            '--json-style=full',
        ]);

        self::assertSame(0, $exitCode, $stderr);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertSame('cleanpeg', $payload['grammar']['format']);
        self::assertSame('NginxConfig', $payload['grammar']['startRule']);
        self::assertSame(1, $payload['query']['count']);
        self::assertSame('Block', $payload['matches'][0]['name']);
        self::assertSame('server', $payload['matches'][0]['semantic']['name']);
        self::assertArrayHasKey('children', $payload['matches'][0]);
    }

    /**
     * Verifies the default JSON style keeps only the essential fields.
     */
    public function testParsesGrammarAndExportsSimpleJsonByDefault(): void
    {
        [$grammarPath, $inputPath] = $this->createCompactLakeFixture();

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'parse',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--query=Lake[kind="lake"]',
        ]);

        self::assertSame(0, $exitCode, $stderr);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertArrayNotHasKey('grammar', $payload);
        self::assertArrayHasKey('matches', $payload);
        self::assertTrue($payload['matches'][0]['lake']);
        self::assertSame('Lake', $payload['matches'][0]['name']);
        self::assertSame('middle', $payload['matches'][0]['text']);
        self::assertArrayHasKey('children', $payload['matches'][0]);

        @unlink($grammarPath);
        @unlink($inputPath);
    }

    /**
     * Verifies the command can write JSON output to a file.
     */
    public function testWritesJsonToAnOutputFile(): void
    {
        [$grammarPath, $inputPath] = $this->createCompactLakeFixture();
        $outputPath = tempnam(sys_get_temp_dir(), 'phpeg-output-');
        if ($outputPath === false) {
            self::fail('Unable to create a temporary output file.');
        }

        unlink($outputPath);

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'parse',
            '--grammar=' . $grammarPath,
            '-i',
            $inputPath,
            '--grammar-format=cleanpeg',
            '--query=Lake[kind="lake"]',
            '--json-style=simple',
            '--output=' . $outputPath,
        ]);

        self::assertSame(0, $exitCode, $stderr);
        self::assertSame('', $stdout);
        self::assertFileExists($outputPath);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success']);
        self::assertSame('Lake', $payload['matches'][0]['name']);

        @unlink($grammarPath);
        @unlink($inputPath);
        @unlink($outputPath);
    }

    /**
     * Verifies the command can write a DOT parse tree file while still emitting JSON to stdout.
     */
    public function testWritesDotParseTreeToAFile(): void
    {
        [$grammarPath, $inputPath] = $this->createCompactLakeFixture();
        $treeOutputPath = tempnam(sys_get_temp_dir(), 'phpeg-tree-');
        if ($treeOutputPath === false) {
            self::fail('Unable to create a temporary tree output file.');
        }

        unlink($treeOutputPath);

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'parse',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--tree-format=dot',
            '--tree-output=' . $treeOutputPath,
            '--json-style=simple',
        ]);

        self::assertSame(0, $exitCode, $stderr);
        self::assertFileExists($treeOutputPath);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success']);
        self::assertStringContainsString('digraph ParseTree {', (string) file_get_contents($treeOutputPath));
        $dot = (string) file_get_contents($treeOutputPath);
        self::assertStringContainsString('  n1 [label="Start\\n0..19\\nbefore middle after", style="rounded,filled", color="#3b82f6", fillcolor="#eff6ff", fontcolor="#1e3a8a"];', $dot);
        self::assertStringContainsString('  n2 [label="Lake\\n7..13\\nmiddle", style="dashed,rounded,filled", color="#2563eb", fillcolor="#dbeafe", fontcolor="#1e3a8a"];', $dot);

        @unlink($grammarPath);
        @unlink($inputPath);
        @unlink($treeOutputPath);
    }

    /**
     * Verifies unsupported tree formats render a clean CLI error.
     */
    public function testFailsWhenTreeFormatIsUnsupported(): void
    {
        [$grammarPath, $inputPath] = $this->createCompactLakeFixture();
        $treeOutputPath = tempnam(sys_get_temp_dir(), 'phpeg-tree-');
        if ($treeOutputPath === false) {
            self::fail('Unable to create a temporary tree output file.');
        }

        unlink($treeOutputPath);

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'parse',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--tree-format=svg',
            '--tree-output=' . $treeOutputPath,
        ]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stderr);
        self::assertStringContainsString('ERROR', $stdout);
        self::assertStringContainsString('Unsupported tree format "svg". Use "dot".', $stdout);
        self::assertFileDoesNotExist($treeOutputPath);

        @unlink($grammarPath);
        @unlink($inputPath);
    }

    /**
     * Verifies tree output is rejected when the output path is missing.
     */
    public function testFailsWhenTreeOutputIsMissing(): void
    {
        [$grammarPath, $inputPath] = $this->createCompactLakeFixture();

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'parse',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--tree-format=dot',
        ]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stderr);
        self::assertStringContainsString('ERROR', $stdout);
        self::assertStringContainsString('Pass --tree-output=path/to/tree.dot when using --tree-format=dot.', $stdout);

        @unlink($grammarPath);
        @unlink($inputPath);
    }

    /**
     * Verifies tree output is rejected when the format is missing.
     */
    public function testFailsWhenTreeFormatIsMissing(): void
    {
        [$grammarPath, $inputPath] = $this->createCompactLakeFixture();
        $treeOutputPath = tempnam(sys_get_temp_dir(), 'phpeg-tree-');
        if ($treeOutputPath === false) {
            self::fail('Unable to create a temporary tree output file.');
        }

        unlink($treeOutputPath);

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'parse',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--tree-output=' . $treeOutputPath,
        ]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stderr);
        self::assertStringContainsString('ERROR', $stdout);
        self::assertStringContainsString('Pass --tree-format=dot when using --tree-output.', $stdout);

        @unlink($grammarPath);
        @unlink($inputPath);
        @unlink($treeOutputPath);
    }

    /**
     * Verifies a parsing failure does not write a DOT file.
     */
    public function testParseFailureDoesNotWriteDotFile(): void
    {
        [$grammarPath, $inputPath] = $this->createCompactLakeFixture();
        $treeOutputPath = tempnam(sys_get_temp_dir(), 'phpeg-tree-');
        if ($treeOutputPath === false) {
            self::fail('Unable to create a temporary tree output file.');
        }

        unlink($treeOutputPath);
        file_put_contents($inputPath, 'before middle');

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'parse',
            '--grammar=' . $grammarPath,
            '--input=' . $inputPath,
            '--grammar-format=cleanpeg',
            '--tree-format=dot',
            '--tree-output=' . $treeOutputPath,
        ]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stderr);
        self::assertStringContainsString('ERROR', $stdout);
        self::assertFileDoesNotExist($treeOutputPath);

        @unlink($grammarPath);
        @unlink($inputPath);
    }

    /**
     * Verifies the command fails when required grammar input is missing.
     */
    public function testFailsWhenGrammarFlagIsMissing(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'parse',
            '--input=' . $this->projectRoot() . '/examples/nginx-config-edit/nginx-config.conf',
        ]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stderr);
        self::assertStringContainsString('ERROR', $stdout);
        self::assertStringContainsString('Missing required grammar path.', $stdout);
    }

    /**
     * Verifies invalid grammar format values render a clean CLI error.
     */
    public function testFailsWhenGrammarFormatIsInvalid(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'parse',
            '--grammar=' . $this->projectRoot() . '/examples/nginx-config-edit/nginx-config-grammar.cleanpeg',
            '--input=' . $this->projectRoot() . '/examples/nginx-config-edit/nginx-config.conf',
            '--grammar-format=pippo',
        ]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stderr);
        self::assertStringContainsString('ERROR', $stdout);
        self::assertStringContainsString('Unsupported grammar format "pippo".', $stdout);
        self::assertStringContainsString('Use --grammar-format=auto, cleanpeg, or peg.', $stdout);
    }

    /**
     * Verifies grammar syntax failures render a clean CLI error.
     */
    public function testFailsWhenGrammarCompilationFails(): void
    {
        $grammarPath = tempnam(sys_get_temp_dir(), 'phpeg-bad-grammar-');
        if ($grammarPath === false) {
            self::fail('Unable to create a temporary grammar file.');
        }

        file_put_contents($grammarPath, "Start = (\n");

        [$exitCode, $stdout, $stderr] = $this->runCommand([
            PHP_BINARY,
            $this->projectRoot() . '/bin/phpeg',
            'parse',
            '--grammar=' . $grammarPath,
            '--input=' . $this->projectRoot() . '/examples/nginx-config-edit/nginx-config.conf',
            '--grammar-format=cleanpeg',
        ]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stderr);
        self::assertStringContainsString('ERROR', $stdout);
        self::assertStringContainsString('Invalid CleanPeg syntax', $stdout);

        @unlink($grammarPath);
    }

    /**
     * Runs a command and returns its exit code and captured streams.
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

    /**
     * Creates a small lake-parsing fixture for the compact output test.
     *
     * @return array{0: string, 1: string}
     */
    private function createCompactLakeFixture(): array
    {
        $grammarPath = tempnam(sys_get_temp_dir(), 'phpeg-grammar-');
        $inputPath = tempnam(sys_get_temp_dir(), 'phpeg-input-');

        if ($grammarPath === false || $inputPath === false) {
            self::fail('Unable to create temporary fixture files.');
        }

        file_put_contents($grammarPath, <<<CLEANPEG
Start = "before" ~ "after" EOF
CLEANPEG);
        file_put_contents($inputPath, 'before middle after');

        return [$grammarPath, $inputPath];
    }
}
