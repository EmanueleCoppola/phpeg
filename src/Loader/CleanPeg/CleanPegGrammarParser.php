<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Loader\CleanPeg;

use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;
use EmanueleCoppola\PHPeg\Error\GrammarSyntaxError;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;
use EmanueleCoppola\PHPeg\Grammar\Grammar;

/**
 * Parses CleanPeg grammar syntax into PHPPeg grammar objects.
 */
class CleanPegGrammarParser
{
    /**
     * @param list<CleanPegToken> $tokens
     */
    private function __construct(
        private readonly array $tokens,
        private readonly GrammarBuilder $builder,
        private readonly ?string $startRule,
        private readonly ?ExpressionInterface $skipExpression,
        private int $index = 0,
    ) {
    }

    /**
     * Parses CleanPeg source text into a grammar.
     */
    public static function parse(string $source, ?string $startRule = null, ?string $skipPattern = '[ \t\r\n]*'): Grammar
    {
        $builder = GrammarBuilder::create();
        $tokens = (new CleanPegTokenizer($source))->tokenize();
        $skipExpression = $skipPattern === null ? null : $builder->regex($skipPattern);
        $parser = new self($tokens, $builder, $startRule, $skipExpression);

        return $parser->parseGrammar();
    }

    /**
     * Reads the full CleanPeg rule list and builds the grammar.
     */
    private function parseGrammar(): Grammar
    {
        $firstRule = null;

        while (!$this->check('EOF')) {
            $this->consumeNewlines();
            if ($this->check('EOF')) {
                break;
            }

            if ($this->isLakeDeclarationStart()) {
                $this->parseLakeDeclaration();
                if (!$this->check('EOF')) {
                    $this->consume('NEWLINE', 'expected end of lake declaration');
                }
                continue;
            }

            $annotations = $this->parseAnnotations();
            $this->consumeNewlines();
            $name = $this->consume('IDENT', 'expected rule name')->lexeme;
            $this->consume('EQUAL', 'expected "=" after rule name');
            $expression = $this->parseExpression();
            if ($annotations['lake']) {
                $this->builder->lakeRule($name, $expression);
            } else {
                $this->builder->rule($name, $expression, $annotations['water']);
                $firstRule ??= $name;
            }

            if (!$this->check('EOF')) {
                $this->consume('NEWLINE', 'expected end of rule');
            }
        }

        if ($firstRule === null) {
            throw new GrammarSyntaxError('CleanPeg', 1, 1, 'grammar does not contain any rule');
        }

        $this->builder->grammar($this->startRule ?? $firstRule);

        return $this->builder->build();
    }

    /**
     * Parses optional `@water` annotations before a rule definition.
     *
     * @return array{water: bool, lake: bool}
     */
    private function parseAnnotations(): array
    {
        $isWater = false;
        $isLake = false;

        while ($this->match('AT')) {
            $annotation = $this->consume('IDENT', 'expected annotation name after "@"')->lexeme;
            if ($annotation === 'water') {
                $isWater = true;
                while ($this->match('NEWLINE')) {
                }
                continue;
            }

            if ($annotation === 'lake') {
                $isLake = true;
                while ($this->match('NEWLINE')) {
                }
                continue;
            }

            $token = $this->peek();
            throw new GrammarSyntaxError('CleanPeg', $token->line, $token->column, sprintf('unsupported annotation "@%s"', $annotation));
        }

        if ($isWater && $isLake) {
            $token = $this->peek();
            throw new GrammarSyntaxError('CleanPeg', $token->line, $token->column, 'annotations "@water" and "@lake" cannot be combined on the same rule');
        }

        return ['water' => $isWater, 'lake' => $isLake];
    }

    /**
     * Parses a named lake profile declaration in the form `<Name> = expression`.
     */
    private function parseLakeDeclaration(): void
    {
        $this->consume('LT', 'expected "<" at the beginning of a lake declaration');
        $name = $this->consume('IDENT', 'expected lake name after "<"')->lexeme;
        $this->consume('GT', 'expected ">" after lake name');
        $this->consume('EQUAL', 'expected "=" after lake name');
        $expression = $this->parseExpression();

        $this->builder->lakeRule($name, $expression);
    }

    /**
     * Parses the top-level expression entry point.
     */
    private function parseExpression(): ExpressionInterface
    {
        return $this->parseChoice();
    }

    /**
     * Parses a choice expression separated by `/`.
     */
    private function parseChoice(): ExpressionInterface
    {
        $sequence = $this->parseSequence();
        $alternatives = [$sequence];

        while ($this->match('SLASH')) {
            $alternatives[] = $this->parseSequence();
        }

        return count($alternatives) === 1 ? $sequence : $this->builder->choice(...$alternatives);
    }

    /**
     * Parses a sequence until the current expression ends.
     */
    private function parseSequence(): ExpressionInterface
    {
        $items = [];

        while ($this->isPrimaryStart()) {
            $items[] = $this->parsePostfix();
        }

        if ($items === []) {
            $token = $this->peek();
            throw new GrammarSyntaxError('CleanPeg', $token->line, $token->column, 'expected expression');
        }

        return count($items) === 1 ? $items[0] : $this->builder->seq(...$items);
    }

    /**
     * Parses repetition postfixes applied to the current term.
     */
    private function parsePostfix(): ExpressionInterface
    {
        $expression = $this->parsePrimary();

        if ($this->match('QUESTION')) {
            return $this->builder->optional($expression);
        }

        if ($this->match('STAR')) {
            return $this->builder->zeroOrMore($expression);
        }

        if ($this->match('PLUS')) {
            return $this->builder->oneOrMore($expression);
        }

        return $expression;
    }

    /**
     * Parses an atomic CleanPeg expression.
     */
    private function parsePrimary(): ExpressionInterface
    {
        if ($this->match('STRING')) {
            return $this->wrapSkippable($this->builder->literal($this->previous()->lexeme));
        }

        if ($this->match('REGEX')) {
            return $this->wrapSkippable($this->builder->regex($this->previous()->lexeme));
        }

        if ($this->match('IDENT')) {
            $name = $this->previous()->lexeme;

            if ($name === 'EOF') {
                return $this->wrapSkippable($this->builder->eof());
            }

            if ($this->match('AT')) {
                return $this->wrapSkippable(
                    $this->builder->capture($name, $this->parsePrimary())
                );
            }

            return $this->wrapSkippable($this->builder->ref($name));
        }

        if ($this->match('TILDE')) {
            return $this->wrapSkippable($this->parseLakeExpression());
        }

        if ($this->match('LT')) {
            return $this->wrapSkippable($this->parseLakeExpression());
        }

        if ($this->match('LPAREN')) {
            $expression = $this->parseExpression();
            $this->consume('RPAREN', 'expected ")" after grouped expression');

            return $expression;
        }

        $token = $this->peek();
        throw new GrammarSyntaxError('CleanPeg', $token->line, $token->column, 'expected expression');
    }

    /**
     * Wraps an expression with the configured skip prefix when needed.
     */
    private function wrapSkippable(ExpressionInterface $expression): ExpressionInterface
    {
        if ($this->skipExpression === null) {
            return $expression;
        }

        return $this->builder->seq($this->skipExpression, $expression);
    }

    /**
     * Detects whether the current token can start a primary expression.
     */
    private function isPrimaryStart(): bool
    {
        if ($this->check('STRING') || $this->check('REGEX') || $this->check('LPAREN')) {
            return true;
        }

        if ($this->check('IDENT') || $this->check('TILDE') || $this->check('LT')) {
            return true;
        }

        return false;
    }

    /**
     * Detects whether the current token starts a lake profile declaration.
     */
    private function isLakeDeclarationStart(): bool
    {
        return $this->check('LT')
            && $this->peekAt(1)->type === 'IDENT'
            && $this->peekAt(2)->type === 'GT'
            && $this->peekAt(3)->type === 'EQUAL';
    }

    /**
     * Parses a lake expression in `~`, `<Name>`, or `<>` form.
     */
    private function parseLakeExpression(): ExpressionInterface
    {
        if ($this->previous()->type === 'TILDE') {
            return $this->builder->lake();
        }

        if ($this->check('GT')) {
            $this->consume('GT', 'expected ">" to close unnamed lake');

            return $this->builder->lake();
        }

        $name = $this->consume('IDENT', 'expected lake name after "<"')->lexeme;
        $this->consume('GT', 'expected ">" after lake name');

        return $this->builder->lake($name);
    }

    /**
     * Consumes consecutive newline tokens between rules.
     */
    private function consumeNewlines(): void
    {
        while ($this->match('NEWLINE')) {
        }
    }

    /**
     * Matches the provided token type at the current cursor.
     */
    private function match(string $type): bool
    {
        if (!$this->check($type)) {
            return false;
        }

        $this->index++;

        return true;
    }

    /**
     * Advances only when the current token matches the expected type.
     */
    private function consume(string $type, string $message): CleanPegToken
    {
        if ($this->check($type)) {
            return $this->tokens[$this->index++];
        }

        $token = $this->peek();
        throw new GrammarSyntaxError('CleanPeg', $token->line, $token->column, $message);
    }

    /**
     * Checks whether the current token type matches.
     */
    private function check(string $type): bool
    {
        return $this->peek()->type === $type;
    }

    /**
     * Returns the current token.
     */
    private function peek(): CleanPegToken
    {
        return $this->tokens[$this->index];
    }

    /**
     * Returns the token immediately before the current cursor.
     */
    private function previous(): CleanPegToken
    {
        return $this->tokens[$this->index - 1];
    }

    /**
     * Returns the token at the provided lookahead distance.
     */
    private function peekAt(int $distance): CleanPegToken
    {
        return $this->tokens[$this->index + $distance] ?? end($this->tokens);
    }
}
