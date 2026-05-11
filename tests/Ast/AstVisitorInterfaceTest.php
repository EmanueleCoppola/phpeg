<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Ast;

use EmanueleCoppola\PHPeg\Ast\AstNodeFactory;
use EmanueleCoppola\PHPeg\Ast\AstTraversalAction;
use EmanueleCoppola\PHPeg\Ast\AstVisitorInterface;
use PHPUnit\Framework\TestCase;

class AstVisitorInterfaceTest extends TestCase
{
    /**
     * Verifies the visitor interface can be used directly by traversal helpers.
     */
    public function testVisitorInterfaceCanDriveTraversal(): void
    {
        $root = (new AstNodeFactory())->node('Root', [
            (new AstNodeFactory())->token('Leaf', 'x'),
        ]);

        $visitor = new class () implements AstVisitorInterface {
            public array $visited = [];

            public function visit(\EmanueleCoppola\PHPeg\Ast\AstNode $node, int $depth): AstTraversalAction
            {
                $this->visited[] = [$node->name(), $depth];

                return AstTraversalAction::Continue;
            }
        };

        $root->traverseDepthFirst($visitor);

        self::assertSame([
            ['Root', 0],
            ['Leaf', 1],
        ], $visitor->visited);
    }
}
