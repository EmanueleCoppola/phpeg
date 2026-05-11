<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Parser;

/**
 * Selects the parser runtime used to evaluate a grammar.
 */
enum ParserRuntimeMode: string
{
    case Auto = 'auto';
    case Packrat = 'packrat';
    case BottomUp = 'bottom-up';
}
