<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Ast;

/**
 * Visitor contract for typed AST node dispatch.
 */
interface AstNodeVisitorInterface
{
    /**
     * @return AstTraversalAction|bool|null
     */
    public function visitNode(AstNode $node, int $depth);

    /**
     * @return AstTraversalAction|bool|null
     */
    public function visitNonterminal(AstNode $node, int $depth);

    /**
     * @return AstTraversalAction|bool|null
     */
    public function visitLeaf(AstNode $node, int $depth);

    /**
     * @return AstTraversalAction|bool|null
     */
    public function visitLake(AstNode $node, int $depth);
}
