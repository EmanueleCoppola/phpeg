<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Ast;

/**
 * Controls how AST traversal continues after visiting a node.
 */
enum AstTraversalAction
{
    case Continue;
    case SkipChildren;
    case Stop;
}
