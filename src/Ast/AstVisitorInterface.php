<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Ast;

/**
 * Visitor contract for AST traversals.
 */
interface AstVisitorInterface
{
    /**
     * @return AstTraversalAction|bool|null
     */
    public function visit(AstNode $node, int $depth);
}
