<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Parser;

use EmanueleCoppola\PHPeg\App\Trace\ParserTraceRecorder;
use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Parser\BottomUp\BottomUpParser;
use EmanueleCoppola\PHPeg\Parser\Packrat\PackratParser;
use EmanueleCoppola\PHPeg\Result\ParseResult;

/**
 * Facade that chooses the parser implementation for a grammar.
 */
class Parser
{
    /**
     * Creates a parser with the provided options.
     */
    public function __construct(
        private readonly ParserOptions $options = new ParserOptions(),
    ) {
    }

    /**
     * Returns a copy of the parser with updated options.
     */
    public function withOptions(ParserOptions $options): self
    {
        return new self($options);
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
        if ($grammar->requiresLeftRecursion()) {
            return (new BottomUpParser($this->options))->parse($grammar, $input, $startRule, $options, $traceRecorder);
        }

        return (new PackratParser($this->options))->parse($grammar, $input, $startRule, $options, $traceRecorder);
    }
}
