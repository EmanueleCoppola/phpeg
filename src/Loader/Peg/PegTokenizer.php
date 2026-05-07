<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Loader\Peg;

use InvalidArgumentException;

/**
 * Tokenizes PEG grammar source text.
 */
class PegTokenizer
{
    private int $offset = 0;

    private int $length;

    /**
     * Initializes a new PegTokenizer instance.
     */
    public function __construct(
        private readonly string $source,
    ) {
        $this->length = strlen($source);
    }

    /**
     * @return list<PegToken>
     */
    public function tokenize(): array
    {
        $tokens = [];

        while (true) {
            $this->skipIgnored();
            if ($this->offset >= $this->length) {
                break;
            }

            $start = $this->offset;

            if ($this->match('<-')) {
                $tokens[] = new PegToken('ARROW', '<-', $start);
                continue;
            }

            $char = $this->source[$this->offset];

            if (isset([
                '/' => 'SLASH',
                '*' => 'STAR',
                '+' => 'PLUS',
                '?' => 'QUESTION',
                '~' => 'TILDE',
                '@' => 'AT',
                '<' => 'LT',
                '>' => 'GT',
                '(' => 'LPAREN',
                ')' => 'RPAREN',
                '.' => 'DOT',
                '&' => 'AND',
                '!' => 'NOT',
            ][$char])) {
                $this->offset++;
                $tokens[] = new PegToken([
                    '/' => 'SLASH',
                    '*' => 'STAR',
                    '+' => 'PLUS',
                    '?' => 'QUESTION',
                    '~' => 'TILDE',
                    '@' => 'AT',
                    '<' => 'LT',
                    '>' => 'GT',
                    '(' => 'LPAREN',
                    ')' => 'RPAREN',
                    '.' => 'DOT',
                    '&' => 'AND',
                    '!' => 'NOT',
                ][$char], $char, $start);
                continue;
            }

            if ($char === '"' || $char === "'") {
                [$lexeme, $ignoreCase] = $this->readString($char);
                $tokens[] = new PegToken('LITERAL', $lexeme, $start, $ignoreCase);
                continue;
            }

            if ($char === '[') {
                [$lexeme, $ignoreCase] = $this->readCharClass();
                $tokens[] = new PegToken('CHAR_CLASS', $lexeme, $start, $ignoreCase);
                continue;
            }

            if (preg_match('/[A-Za-z_]/A', $char) === 1) {
                $tokens[] = new PegToken('IDENT', $this->readIdentifier(), $start);
                continue;
            }

            throw new InvalidArgumentException(sprintf('Unexpected character "%s" at offset %d.', $char, $this->offset));
        }

        $tokens[] = new PegToken('EOF', '', $this->offset);

        return $tokens;
    }

    /**
     * Skips whitespace and comments.
     */
    private function skipIgnored(): void
    {
        while ($this->offset < $this->length) {
            $char = $this->source[$this->offset];
            if (preg_match('/\s/A', $char) === 1) {
                $this->offset++;
                continue;
            }

            if ($this->match('//')) {
                while ($this->offset < $this->length && $this->source[$this->offset] !== "\n") {
                    $this->offset++;
                }
                continue;
            }

            if ($char === '#') {
                while ($this->offset < $this->length && $this->source[$this->offset] !== "\n") {
                    $this->offset++;
                }
                continue;
            }

            break;
        }
    }

    /**
     * Matches the provided token text at the current cursor.
     */
    private function match(string $value): bool
    {
        if (substr_compare($this->source, $value, $this->offset, strlen($value)) !== 0) {
            return false;
        }

        $this->offset += strlen($value);

        return true;
    }

    /**
     * Reads an identifier from the current cursor.
     */
    private function readIdentifier(): string
    {
        $start = $this->offset;
        $this->offset++;

        while ($this->offset < $this->length && preg_match('/[A-Za-z0-9_]/A', $this->source[$this->offset]) === 1) {
            $this->offset++;
        }

        return substr($this->source, $start, $this->offset - $start);
    }

    /**
     * Reads a quoted string literal from the current cursor.
     *
     * @return array{0:string,1:?bool}
     */
    private function readString(string $quote): array
    {
        $this->offset++;
        $value = '';

        while ($this->offset < $this->length) {
            $char = $this->source[$this->offset];
            if ($char === '\\') {
                if ($this->offset + 1 >= $this->length) {
                    throw new InvalidArgumentException('Unterminated escape sequence in string literal.');
                }

                $value .= '\\' . $this->source[$this->offset + 1];
                $this->offset += 2;
                continue;
            }

            if ($char === $quote) {
                $this->offset++;

                return [stripcslashes($value), $this->consumeIgnoreCaseSuffix()];
            }

            $value .= $char;
            $this->offset++;
        }

        throw new InvalidArgumentException('Unterminated string literal in PEG grammar.');
    }

    /**
     * Reads a character class literal from the current cursor.
     *
     * @return array{0:string,1:?bool}
     */
    private function readCharClass(): array
    {
        $start = $this->offset;
        $this->offset++;

        while ($this->offset < $this->length) {
            $char = $this->source[$this->offset];
            if ($char === '\\') {
                $this->offset += 2;
                continue;
            }

            if ($char === ']') {
                $this->offset++;

                return [substr($this->source, $start, $this->offset - $start), $this->consumeIgnoreCaseSuffix()];
            }

            $this->offset++;
        }

        throw new InvalidArgumentException('Unterminated character class in PEG grammar.');
    }

    /**
     * Consumes an optional trailing `i` suffix used for case-insensitive terminals.
     */
    private function consumeIgnoreCaseSuffix(): ?bool
    {
        if ($this->offset < $this->length && $this->source[$this->offset] === 'i') {
            $this->offset++;

            return true;
        }

        return null;
    }
}
