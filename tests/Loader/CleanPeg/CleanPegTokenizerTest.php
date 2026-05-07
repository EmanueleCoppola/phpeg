<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Loader\CleanPeg;

use EmanueleCoppola\PHPeg\Loader\CleanPeg\CleanPegTokenizer;
use PHPUnit\Framework\TestCase;

class CleanPegTokenizerTest extends TestCase
{
    /**
     * Verifies tokenization of a small CleanPeg snippet.
     */
    public function testTokenizesCleanPegSource(): void
    {
        $tokens = (new CleanPegTokenizer("Json = \"a\"i\nRegex = r'[A-Z]+'i\n"))->tokenize();

        self::assertSame(['IDENT', 'EQUAL', 'STRING', 'NEWLINE', 'IDENT', 'EQUAL', 'REGEX', 'NEWLINE', 'EOF'], array_map(static fn ($token): string => $token->type, $tokens));
        self::assertSame('Json', $tokens[0]->lexeme);
        self::assertSame('a', $tokens[2]->lexeme);
        self::assertTrue($tokens[2]->ignoreCase);
        self::assertTrue($tokens[6]->ignoreCase);
    }
}
