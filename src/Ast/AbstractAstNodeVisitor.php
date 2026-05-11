<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Ast;

/**
 * Convenience base class for typed AST node visitors.
 */
abstract class AbstractAstNodeVisitor implements AstNodeVisitorInterface
{
    /**
     * Visits any AST node kind.
     */
    public function visitNode(AstNode $node, int $depth)
    {
        return AstTraversalAction::Continue;
    }

    /**
     * Visits a nonterminal node.
     */
    public function visitNonterminal(AstNode $node, int $depth)
    {
        return $this->visitNode($node, $depth);
    }

    /**
     * Visits a leaf node.
     */
    public function visitLeaf(AstNode $node, int $depth)
    {
        return $this->visitNode($node, $depth);
    }

    /**
     * Visits a lake node.
     */
    public function visitLake(AstNode $node, int $depth)
    {
        return $this->visitNode($node, $depth);
    }
}
