<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Ast;

use EmanueleCoppola\PHPeg\Ast\AstNodeFactory;
use EmanueleCoppola\PHPeg\Ast\AstTraversalAction;
use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;
use PHPUnit\Framework\TestCase;

class AstTraversalTest extends TestCase
{
    /**
     * Verifies depth-first traversal visits nodes in pre-order and honors skip/stop actions.
     */
    public function testDepthFirstTraversalSupportsControlFlow(): void
    {
        $root = $this->buildSampleTree();
        $visited = [];

        $root->traverseDepthFirst(function ($node, $depth) use (&$visited) {
            $visited[] = [$node->name(), $depth];

            if ($node->name() === 'Left') {
                return AstTraversalAction::SkipChildren;
            }

            if ($node->name() === 'Right') {
                return AstTraversalAction::Stop;
            }

            return null;
        });

        self::assertSame([
            ['Root', 0],
            ['Left', 1],
            ['Right', 1],
        ], $visited);
    }

    /**
     * Verifies breadth-first traversal visits siblings before descendants.
     */
    public function testBreadthFirstTraversalVisitsLevelByLevel(): void
    {
        $root = $this->buildSampleTree();
        $visited = [];

        $root->traverseBreadthFirst(function ($node, $depth) use (&$visited) {
            $visited[] = [$node->name(), $depth];

            return null;
        });

        self::assertSame([
            ['Root', 0],
            ['Left', 1],
            ['Right', 1],
            ['LeftLeaf', 2],
            ['RightLeaf', 2],
        ], $visited);
    }

    /**
     * Verifies ParsedDocument forwards traversal calls to the root AST node.
     */
    public function testParsedDocumentTraversalDelegatesToRoot(): void
    {
        $builder = GrammarBuilder::create();
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

        $document->traverseDepthFirst(function ($node) use (&$visited) {
            $visited[] = $node->name();

            return null;
        });

        self::assertSame(['Root', 'Left', 'Right'], $visited);
    }

    /**
     * Builds a small detached tree for traversal assertions.
     */
    private function buildSampleTree()
    {
        $factory = new AstNodeFactory();

        return $factory->node('Root', [
            $factory->node('Left', [
                $factory->token('LeftLeaf', 'a'),
            ]),
            $factory->node('Right', [
                $factory->token('RightLeaf', 'b'),
            ]),
        ]);
    }
}
