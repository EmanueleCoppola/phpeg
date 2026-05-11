<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Ast;

use EmanueleCoppola\PHPeg\Ast\AbstractAstNodeVisitor;
use EmanueleCoppola\PHPeg\Ast\AstNodeFactory;
use EmanueleCoppola\PHPeg\Ast\AstTraversalAction;
use PHPUnit\Framework\TestCase;

class AstNodeVisitorTest extends TestCase
{
    /**
     * Verifies the typed visitor dispatches by semantic node kind.
     */
    public function testAcceptDispatchesByNodeKind(): void
    {
        $root = (new AstNodeFactory())->node('Root', [
            (new AstNodeFactory())->token('Leaf', 'x'),
        ]);
        $visited = [];

        $visitor = new class ($visited) extends AbstractAstNodeVisitor {
            public array $visited;

            public function __construct(array &$visited)
            {
                $this->visited = &$visited;
            }

            public function visitNode(\EmanueleCoppola\PHPeg\Ast\AstNode $node, int $depth)
            {
                $this->visited[] = ['node', $node->name(), $depth];

                return AstTraversalAction::Continue;
            }

            public function visitNonterminal(\EmanueleCoppola\PHPeg\Ast\AstNode $node, int $depth)
            {
                $this->visited[] = ['nonterminal', $node->name(), $depth];

                return AstTraversalAction::Continue;
            }

            public function visitLeaf(\EmanueleCoppola\PHPeg\Ast\AstNode $node, int $depth)
            {
                $this->visited[] = ['leaf', $node->name(), $depth];

                return AstTraversalAction::Continue;
            }
        };

        $root->accept($visitor);
        $root->children()[0]->accept($visitor, 1);

        self::assertSame([
            ['nonterminal', 'Root', 0],
            ['leaf', 'Leaf', 1],
        ], $visited);
    }

    /**
     * Verifies ParsedDocument can traverse with a typed visitor.
     */
    public function testParsedDocumentAcceptTraversesTheTree(): void
    {
        $builder = \EmanueleCoppola\PHPeg\Builder\GrammarBuilder::create();
        $grammar = $builder
            ->grammar('Root')
            ->rule('Root', $builder->seq(
                $builder->ref('Left'),
                $builder->ref('Right'),
            ))
            ->rule('Left', $builder->literal('a'))
            ->rule('Right', $builder->literal('b'))
            ->build();

        $document = $grammar->parseDocument('ab');
        $visited = [];

        $visitor = new class ($visited) extends AbstractAstNodeVisitor {
            public array $visited;

            public function __construct(array &$visited)
            {
                $this->visited = &$visited;
            }

            public function visitNode(\EmanueleCoppola\PHPeg\Ast\AstNode $node, int $depth)
            {
                $this->visited[] = [$node->name(), $depth];

                return AstTraversalAction::Continue;
            }
        };

        $document->accept($visitor);

        self::assertSame([
            ['Root', 0],
            ['Left', 1],
            ['Right', 1],
        ], $visited);
    }
}
