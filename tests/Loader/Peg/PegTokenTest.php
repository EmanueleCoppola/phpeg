<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Loader\Peg;

use EmanueleCoppola\PHPeg\Loader\Peg\PegToken;
use PHPUnit\Framework\TestCase;

class PegTokenTest extends TestCase
{
    /**
     * Verifies token payload accessors.
     */
    public function testExposesTokenFields(): void
    {
        $token = new PegToken('IDENT', 'Start', 3);

        self::assertSame('IDENT', $token->type);
        self::assertSame('Start', $token->lexeme);
        self::assertSame(3, $token->offset);
        self::assertNull($token->ignoreCase);
    }
}
