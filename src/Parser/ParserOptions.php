<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Parser;

use RuntimeException;

/**
 * Stores parse-time performance and diagnostics options.
 */
class ParserOptions
{
    /**
     * Creates a parser option set.
     */
    public function __construct(
        private readonly bool $memoizationEnabled = true,
        private readonly ?int $maxCacheEntries = null,
        private readonly bool $optimizeErrors = false,
        private readonly bool $reuseEmptyMatches = false,
        private readonly bool $lazyNodeText = true,
        private readonly ParserRuntimeMode $runtimeMode = ParserRuntimeMode::Auto,
    ) {
        if ($maxCacheEntries !== null && $maxCacheEntries < 0) {
            throw new RuntimeException('maxCacheEntries must be greater than or equal to zero.');
        }
    }

    /**
     * Returns the default parser options.
     */
    public static function defaults(): self
    {
        return new self();
    }

    /**
     * Returns whether rule memoization is enabled.
     */
    public function memoizationEnabled(): bool
    {
        return $this->memoizationEnabled;
    }

    /**
     * Returns the maximum number of memoized entries, or null when unbounded.
     */
    public function maxCacheEntries(): ?int
    {
        return $this->maxCacheEntries;
    }

    /**
     * Returns whether error tracking is optimized for successful parses.
     */
    public function optimizeErrors(): bool
    {
        return $this->optimizeErrors;
    }

    /**
     * Returns whether zero-width matches should be cached by offset.
     */
    public function reuseEmptyMatches(): bool
    {
        return $this->reuseEmptyMatches;
    }

    /**
     * Returns whether original AST node text is loaded lazily from the input buffer.
     */
    public function lazyNodeText(): bool
    {
        return $this->lazyNodeText;
    }

    /**
     * Returns the configured parser runtime mode.
     */
    public function runtimeMode(): ParserRuntimeMode
    {
        return $this->runtimeMode;
    }

    /**
     * Returns a copy with memoization toggled.
     */
    public function withMemoization(bool $enabled): self
    {
        return new self(
            memoizationEnabled: $enabled,
            maxCacheEntries: $this->maxCacheEntries,
            optimizeErrors: $this->optimizeErrors,
            reuseEmptyMatches: $this->reuseEmptyMatches,
            lazyNodeText: $this->lazyNodeText,
            runtimeMode: $this->runtimeMode,
        );
    }

    /**
     * Returns a copy with an updated memoization cache limit.
     */
    public function withMaxCacheEntries(?int $maxCacheEntries): self
    {
        return new self(
            memoizationEnabled: $this->memoizationEnabled,
            maxCacheEntries: $maxCacheEntries,
            optimizeErrors: $this->optimizeErrors,
            reuseEmptyMatches: $this->reuseEmptyMatches,
            lazyNodeText: $this->lazyNodeText,
            runtimeMode: $this->runtimeMode,
        );
    }

    /**
     * Returns a copy with optimized error tracking toggled.
     */
    public function withOptimizeErrors(bool $enabled): self
    {
        return new self(
            memoizationEnabled: $this->memoizationEnabled,
            maxCacheEntries: $this->maxCacheEntries,
            optimizeErrors: $enabled,
            reuseEmptyMatches: $this->reuseEmptyMatches,
            lazyNodeText: $this->lazyNodeText,
            runtimeMode: $this->runtimeMode,
        );
    }

    /**
     * Returns a copy with zero-width match reuse toggled.
     */
    public function withReuseEmptyMatches(bool $enabled): self
    {
        return new self(
            memoizationEnabled: $this->memoizationEnabled,
            maxCacheEntries: $this->maxCacheEntries,
            optimizeErrors: $this->optimizeErrors,
            reuseEmptyMatches: $enabled,
            lazyNodeText: $this->lazyNodeText,
            runtimeMode: $this->runtimeMode,
        );
    }

    /**
     * Returns a copy with lazy original node text toggled.
     */
    public function withLazyNodeText(bool $enabled): self
    {
        return new self(
            memoizationEnabled: $this->memoizationEnabled,
            maxCacheEntries: $this->maxCacheEntries,
            optimizeErrors: $this->optimizeErrors,
            reuseEmptyMatches: $this->reuseEmptyMatches,
            lazyNodeText: $enabled,
            runtimeMode: $this->runtimeMode,
        );
    }

    /**
     * Returns a copy with an updated parser runtime mode.
     */
    public function withRuntimeMode(ParserRuntimeMode $runtimeMode): self
    {
        return new self(
            memoizationEnabled: $this->memoizationEnabled,
            maxCacheEntries: $this->maxCacheEntries,
            optimizeErrors: $this->optimizeErrors,
            reuseEmptyMatches: $this->reuseEmptyMatches,
            lazyNodeText: $this->lazyNodeText,
            runtimeMode: $runtimeMode,
        );
    }

    /**
     * Returns a human-readable summary for diagnostics and benchmarks.
     *
     * @return array<string, bool|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'memoization' => $this->memoizationEnabled,
            'maxCacheEntries' => $this->maxCacheEntries,
            'optimizeErrors' => $this->optimizeErrors,
            'reuseEmptyMatches' => $this->reuseEmptyMatches,
            'lazyNodeText' => $this->lazyNodeText,
            'runtimeMode' => $this->runtimeMode->value,
        ];
    }
}
