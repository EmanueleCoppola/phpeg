<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Loader\Peg;

use InvalidArgumentException;
use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;
use EmanueleCoppola\PHPeg\Grammar\Grammar;

/**
 * Parses classic PEG grammar syntax into PHPeg grammar objects.
 */
class PegGrammarParser
{
    /**
     * @param list<PegToken> $tokens
     */
    private function __construct(
        private readonly array $tokens,
        private readonly GrammarBuilder $builder,
        private int $index = 0,
    ) {
    }

    /**
     * Parses a grammar from source text.
     */
    public static function parse(string $source): Grammar
    {
        $builder = GrammarBuilder::create();
        $tokens = (new PegTokenizer($source))->tokenize();
        $parser = new self($tokens, $builder);

        return $parser->parseGrammar();
    }

    /**
     * Reads the full rule list and builds the grammar.
     */
    private function parseGrammar(): Grammar
    {
        $firstRule = null;
        while (!$this->check('EOF')) {
            if ($this->isLakeDeclarationStart()) {
                $this->parseLakeDeclaration();
                continue;
            }

            $annotations = $this->parseAnnotations();
            $name = $this->consume('IDENT', 'Expected rule name.')->lexeme;
            $this->consume('ARROW', 'Expected "<-" after rule name.');
            $expression = $this->parseExpression();
            if ($annotations['lake']) {
                $this->builder->lakeRule($name, $expression);
            } else {
                $this->builder->rule($name, $expression, $annotations['water'], $annotations['ignoreCase']);
                $firstRule ??= $name;
            }
        }

        if ($firstRule === null) {
            throw new InvalidArgumentException('PEG grammar does not contain any rule.');
        }

        $this->builder->grammar($firstRule);

        return $this->builder->build();
    }

    /**
     * Parses optional annotations before a rule definition.
     *
     * @return array{water: bool, lake: bool, ignoreCase: ?bool}
     */
    private function parseAnnotations(): array
    {
        $isWater = false;
        $isLake = false;
        $ignoreCase = null;

        while ($this->match('AT')) {
            $annotation = $this->consume('IDENT', 'Expected annotation name after "@".')->lexeme;
            if ($annotation === 'water') {
                $isWater = true;
                continue;
            }

            if ($annotation === 'lake') {
                $isLake = true;
                continue;
            }

            if ($annotation === 'insensitive') {
                if ($ignoreCase === false) {
                    throw new InvalidArgumentException('PEG annotations "@insensitive" and "@sensitive" cannot be combined on the same rule.');
                }

                $ignoreCase = true;
                continue;
            }

            if ($annotation === 'sensitive') {
                if ($ignoreCase === true) {
                    throw new InvalidArgumentException('PEG annotations "@insensitive" and "@sensitive" cannot be combined on the same rule.');
                }

                $ignoreCase = false;
                continue;
            }

            throw new InvalidArgumentException(sprintf('Unsupported PEG annotation "@%s".', $annotation));
        }

        if ($isWater && $isLake) {
            throw new InvalidArgumentException('PEG annotations "@water" and "@lake" cannot be combined on the same rule.');
        }

        return ['water' => $isWater, 'lake' => $isLake, 'ignoreCase' => $ignoreCase];
    }

    /**
     * Parses a named lake profile declaration in the form `<Name> <- expression`.
     */
    private function parseLakeDeclaration(): void
    {
        $this->consume('LT', 'Expected "<" at the beginning of a lake declaration.');
        $name = $this->consume('IDENT', 'Expected lake name after "<".')->lexeme;
        $this->consume('GT', 'Expected ">" after lake name.');
        $this->consume('ARROW', 'Expected "<-" after lake name.');
        $expression = $this->parseExpression();

        $this->builder->lakeRule($name, $expression);
    }

    /**
     * Parses a choice expression separated by `/`.
     */
    private function parseExpression(): ExpressionInterface
    {
        $sequence = $this->parseSequence();
        $alternatives = [$sequence];

        while ($this->match('SLASH')) {
            $alternatives[] = $this->parseSequence();
        }

        return count($alternatives) === 1 ? $sequence : $this->builder->choice(...$alternatives);
    }

    /**
     * Parses a PEG sequence until the current branch ends.
     */
    private function parseSequence(): ExpressionInterface
    {
        $items = [];
        while (!$this->check('SLASH') && !$this->check('RPAREN') && !$this->check('EOF') && !$this->check('AT') && !$this->isRuleStart()) {
            $items[] = $this->parsePrefix();
        }

        return count($items) === 1 ? $items[0] : $this->builder->seq(...$items);
    }

    /**
     * Parses unary lookahead prefixes.
     */
    private function parsePrefix(): ExpressionInterface
    {
        if ($this->match('AND')) {
            return $this->builder->and($this->parseSuffix());
        }

        if ($this->match('NOT')) {
            return $this->builder->not($this->parseSuffix());
        }

        return $this->parseSuffix();
    }

    /**
     * Parses repetition postfixes applied to the current term.
     */
    private function parseSuffix(): ExpressionInterface
    {
        $expression = $this->parsePrimary();

        if ($this->match('STAR')) {
            return $this->builder->zeroOrMore($expression);
        }

        if ($this->match('PLUS')) {
            return $this->builder->oneOrMore($expression);
        }

        if ($this->match('QUESTION')) {
            return $this->builder->optional($expression);
        }

        return $expression;
    }

    /**
     * Parses the next atomic expression or grouping.
     */
    private function parsePrimary(): ExpressionInterface
    {
        if ($this->match('LITERAL')) {
            $token = $this->previous();

            return $this->builder->literal($token->lexeme, $token->ignoreCase);
        }

        if ($this->match('CHAR_CLASS')) {
            $token = $this->previous();

            return $this->builder->charClass($token->lexeme, $token->ignoreCase);
        }

        if ($this->match('DOT')) {
            return $this->builder->any();
        }

        if ($this->match('IDENT')) {
            return $this->builder->ref($this->previous()->lexeme);
        }

        if ($this->match('TILDE')) {
            return $this->parseLakeExpression();
        }

        if ($this->match('LT')) {
            return $this->parseLakeExpression();
        }

        if ($this->match('LPAREN')) {
            $expression = $this->parseExpression();
            $this->consume('RPAREN', 'Expected ")" after grouped expression.');

            return $expression;
        }

        throw new InvalidArgumentException(sprintf('Unexpected token "%s" in PEG expression.', $this->peek()->type));
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
            $this->consume('GT', 'Expected ">" to close unnamed lake.');

            return $this->builder->lake();
        }

        $name = $this->consume('IDENT', 'Expected lake name after "<".')->lexeme;
        $this->consume('GT', 'Expected ">" after lake name.');

        return $this->builder->lake($name);
    }

    /**
     * Detects whether the current token starts a new rule declaration.
     */
    private function isRuleStart(): bool
    {
        return $this->check('IDENT') && $this->peekNext()->type === 'ARROW';
    }

    /**
     * Detects whether the current token starts a lake profile declaration.
     */
    private function isLakeDeclarationStart(): bool
    {
        return $this->check('LT')
            && $this->peekAt(1)->type === 'IDENT'
            && $this->peekAt(2)->type === 'GT'
            && $this->peekAt(3)->type === 'ARROW';
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
    private function consume(string $type, string $message): PegToken
    {
        if ($this->check($type)) {
            return $this->tokens[$this->index++];
        }

        throw new InvalidArgumentException($message);
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
    private function peek(): PegToken
    {
        return $this->tokens[$this->index];
    }

    /**
     * Returns the next token when available.
     */
    private function peekNext(): PegToken
    {
        return $this->tokens[$this->index + 1] ?? end($this->tokens);
    }

    /**
     * Returns the token at the provided lookahead distance.
     */
    private function peekAt(int $distance): PegToken
    {
        return $this->tokens[$this->index + $distance] ?? end($this->tokens);
    }

    /**
     * Returns the token immediately before the current cursor.
     */
    private function previous(): PegToken
    {
        return $this->tokens[$this->index - 1];
    }
}
