<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Parser;

use EmanueleCoppola\PHPeg\Parser\ParserOptions;
use EmanueleCoppola\PHPeg\Parser\ParserRuntimeMode;
use PHPUnit\Framework\TestCase;

class ParserOptionsTest extends TestCase
{
    /**
     * Verifies the default parser options use the memory-safe baseline configuration.
     */
    public function testDefaultsExposeCompatibilitySettings(): void
    {
        $options = ParserOptions::defaults();

        self::assertTrue($options->memoizationEnabled());
        self::assertNull($options->maxCacheEntries());
        self::assertFalse($options->optimizeErrors());
        self::assertFalse($options->reuseEmptyMatches());
        self::assertTrue($options->lazyNodeText());
        self::assertSame(ParserRuntimeMode::Auto, $options->runtimeMode());
    }

    /**
     * Verifies individual option toggles return updated immutable copies.
     */
    public function testSupportsExplicitOptionToggles(): void
    {
        $options = ParserOptions::defaults()
            ->withMemoization(false)
            ->withMaxCacheEntries(512)
            ->withOptimizeErrors(true)
            ->withReuseEmptyMatches(true)
            ->withLazyNodeText(true)
            ->withRuntimeMode(ParserRuntimeMode::BottomUp);

        self::assertFalse($options->memoizationEnabled());
        self::assertSame(512, $options->maxCacheEntries());
        self::assertTrue($options->optimizeErrors());
        self::assertTrue($options->reuseEmptyMatches());
        self::assertTrue($options->lazyNodeText());
        self::assertSame(ParserRuntimeMode::BottomUp, $options->runtimeMode());
    }
}
