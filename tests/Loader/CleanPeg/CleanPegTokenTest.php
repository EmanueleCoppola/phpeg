<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Loader\CleanPeg;

use EmanueleCoppola\PHPeg\Loader\CleanPeg\CleanPegToken;
use PHPUnit\Framework\TestCase;

class CleanPegTokenTest extends TestCase
{
    /**
     * Verifies token payload accessors.
     */
    public function testExposesTokenFields(): void
    {
        $token = new CleanPegToken('IDENT', 'Json', 2, 5);

        self::assertSame('IDENT', $token->type);
        self::assertSame('Json', $token->lexeme);
        self::assertSame(2, $token->line);
        self::assertSame(5, $token->column);
        self::assertNull($token->ignoreCase);
    }
}
