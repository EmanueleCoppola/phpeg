# Walking and Visiting

PHPeg exposes two different traversal surfaces:

- the parsed AST / document tree
- the grammar's PEG expression graph

They solve different problems.

## Pick The Right Layer

Use AST walking when you want to inspect or transform parsed source:

- `ParsedDocument::traverseDepthFirst()`
- `ParsedDocument::traverseBreadthFirst()`
- `ParsedDocument::traverseNonterminals()`
- `ParsedDocument::traverseLeaves()`
- `ParsedDocument::traverseLakeNodes()`
- `ParsedDocument::accept(AstNodeVisitorInterface)`

Use grammar walking when you want to inspect the PEG model itself:

- `Grammar::traverseExpressions()`
- `ExpressionVisitorInterface`

## AST Walking

`AstNode` and `ParsedDocument` support callback-based walking and visitor-based walking.

### Callback Traversal

`traverseDepthFirst()` walks nodes in pre-order depth-first order.
`traverseBreadthFirst()` walks level by level.

The callback receives:

- the current node
- the current depth

The callback may return:

- `null` or `true` to continue
- `false` to stop
- `AstTraversalAction::SkipChildren` to skip the current node's descendants
- `AstTraversalAction::Stop` to stop the entire traversal

Example:

```php
use EmanueleCoppola\PHPeg\Ast\AstTraversalAction;

$document->traverseDepthFirst(function ($node, int $depth) {
    echo str_repeat('  ', $depth) . $node->name() . PHP_EOL;

    return AstTraversalAction::Continue;
});
```

### Filtered Traversal Helpers

PHPeg also exposes convenience helpers for common subsets:

- `traverseNonterminals()` visits nodes that have children
- `traverseLeaves()` visits nodes without children
- `traverseLakeNodes()` visits nodes marked as lake nodes

These helpers are wrappers over the depth-first traversal.

### Visitor Pattern

If you prefer an object-oriented style, use `AstNodeVisitorInterface`.

`AbstractAstNodeVisitor` gives you a simple default:

- override `visitNode()` to handle all node kinds at once
- override `visitNonterminal()`, `visitLeaf()`, or `visitLake()` when you need special cases

Example:

```php
use EmanueleCoppola\PHPeg\Ast\AbstractAstNodeVisitor;
use EmanueleCoppola\PHPeg\Ast\AstTraversalAction;

final class MyVisitor extends AbstractAstNodeVisitor
{
    public function visitNode(\EmanueleCoppola\PHPeg\Ast\AstNode $node, int $depth)
    {
        echo str_repeat('  ', $depth) . $node->name() . PHP_EOL;

        return AstTraversalAction::Continue;
    }
}

$document->accept(new MyVisitor());
```

## Grammar Walking

`Grammar::traverseExpressions()` walks the PEG expression graph reachable from the grammar.

This is useful when you want to inspect:

- rule dependencies
- nested PEG operators
- lake profiles
- recursive grammar structure

The visitor receives PEG expression nodes such as:

- `SequenceExpression`
- `ChoiceExpression`
- `RuleReferenceExpression`
- `LiteralExpression`
- `RegexExpression`
- `LakeExpression`

Example:

```php
use EmanueleCoppola\PHPeg\Expression\AbstractExpressionVisitor;
use EmanueleCoppola\PHPeg\Expression\ExpressionInterface;

final class DumpExpressions extends AbstractExpressionVisitor
{
    public function visitExpression(ExpressionInterface $expression, int $depth): void
    {
        echo str_repeat('  ', $depth) . $expression->describe() . PHP_EOL;
    }
}

$grammar->traverseExpressions(new DumpExpressions(), 'Expr');
```

## Walking vs Querying

Use `query()` when you want to select a subset of nodes by selector.

Use traversal when you want to inspect:

- the full tree
- a structured subset
- the grammar graph
- node order
- node depth

Traversal is the lower-level primitive.
Query is the higher-level selection tool.
