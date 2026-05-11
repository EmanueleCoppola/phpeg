<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Grammar;

use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;
use EmanueleCoppola\PHPeg\Expression\AbstractExpressionVisitor;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;
use PHPUnit\Framework\TestCase;

class GrammarExpressionTraversalTest extends TestCase
{
    /**
     * Verifies grammar traversal reaches referenced rules and keeps cycle-safe visitation.
     */
    public function testTraversesExpressionGraphThroughRuleReferences(): void
    {
        $builder = GrammarBuilder::create();
        $grammar = $builder
            ->grammar('Expr')
            ->rule('Expr', $builder->choice(
                $builder->seq($builder->ref('Expr'), $builder->literal('+'), $builder->ref('Term')),
                $builder->ref('Term'),
            ))
            ->rule('Term', $builder->choice(
                $builder->literal('x'),
                $builder->literal('y'),
            ))
            ->build();

        $visited = [];

        $grammar->traverseExpressions(new class ($visited) extends AbstractExpressionVisitor {
            public array $visited;

            public function __construct(array &$visited)
            {
                $this->visited = &$visited;
            }

            public function visitExpression(ExpressionInterface $expression, int $depth): void
            {
                $this->visited[] = [$expression->describe(), $depth];
            }
        }, 'Expr');

        self::assertSame([
            ['choice', 0],
            ['sequence', 1],
            ['<Expr>', 2],
            ['"+"', 2],
            ['<Term>', 2],
            ['choice', 3],
            ['"x"', 4],
            ['"y"', 4],
            ['<Term>', 1],
        ], $visited);
    }
}
