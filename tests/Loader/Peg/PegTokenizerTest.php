<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Loader\Peg;

use EmanueleCoppola\PHPeg\Loader\Peg\PegTokenizer;
use PHPUnit\Framework\TestCase;

class PegTokenizerTest extends TestCase
{
    /**
     * Verifies tokenization of a small PEG snippet.
     */
    public function testTokenizesPegSource(): void
    {
        $tokens = (new PegTokenizer('Start <- "a"i / [A-Z]i .'))->tokenize();

        self::assertSame(['IDENT', 'ARROW', 'LITERAL', 'SLASH', 'CHAR_CLASS', 'DOT', 'EOF'], array_map(static fn ($token): string => $token->type, $tokens));
        self::assertSame('Start', $tokens[0]->lexeme);
        self::assertSame('a', $tokens[2]->lexeme);
        self::assertTrue($tokens[2]->ignoreCase);
        self::assertTrue($tokens[4]->ignoreCase);
    }
}
