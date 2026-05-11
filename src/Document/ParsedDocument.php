<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Document;

use EmanueleCoppola\PHPeg\Ast\AstNode;
use EmanueleCoppola\PHPeg\Ast\AstNodeCollection;
use EmanueleCoppola\PHPeg\Ast\AstNodeVisitorInterface;
use EmanueleCoppola\PHPeg\Ast\AstSelectorParser;
use EmanueleCoppola\PHPeg\Ast\AstSelectorStep;
use EmanueleCoppola\PHPeg\Ast\AstVisitorInterface;
use EmanueleCoppola\PHPeg\Ast\AstTraversalAction;
use EmanueleCoppola\PHPeg\Grammar\Grammar;
use EmanueleCoppola\PHPeg\Printer\PrintPolicy;
use EmanueleCoppola\PHPeg\Printer\SourcePreservingPrinter;
use EmanueleCoppola\PHPeg\Result\ParseResult;

/**
 * Editable parsed document rooted at a source-aware AST.
 */
class ParsedDocument
{
    private bool $modified = false;

    /**
     * Initializes a new ParsedDocument instance.
     */
    public function __construct(
        private readonly Grammar $grammar,
        private readonly string $source,
        private readonly AstNode $root,
    ) {
        $this->root->attachDocument($this);
    }

    /**
     * Returns the parsed root node.
     */
    public function root(): AstNode
    {
        return $this->root;
    }

    /**
     * Returns the original source text.
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * Returns whether the document has been modified.
     */
    public function isModified(): bool
    {
        return $this->modified;
    }

    /**
     * Marks the document as modified.
     */
    public function markModified(): void
    {
        $this->modified = true;
    }

    /**
     * Returns the AST nodes matching the selector.
     */
    public function query(string $selector, ?AstNode $scope = null): AstNodeCollection
    {
        $selectorAst = AstSelectorParser::parse($selector);
        $current = [$scope ?? $this->root];

        foreach ($selectorAst->steps() as $index => $step) {
            $next = [];

            foreach ($current as $node) {
                $candidates = $step->combinator() === 'child' && $index > 0
                    ? $node->directChildren()
                    : $node->descendantsAndSelf();

                foreach ($candidates as $candidate) {
                    if ($this->matchesStep($candidate, $step)) {
                        $next[] = $candidate;
                    }
                }
            }

            $current = $this->applyPseudo($this->deduplicate($next), $step);
        }

        return new AstNodeCollection($current, $this);
    }

    /**
     * Traverses the document tree in depth-first pre-order.
     *
     * @param callable(AstNode, int): (AstTraversalAction|bool|null)|AstVisitorInterface $visitor
     */
    public function traverseDepthFirst(callable|AstVisitorInterface $visitor, bool $includeRoot = true): void
    {
        $this->root->traverseDepthFirst($visitor, $includeRoot);
    }

    /**
     * Traverses the document tree in breadth-first order.
     *
     * @param callable(AstNode, int): (AstTraversalAction|bool|null)|AstVisitorInterface $visitor
     */
    public function traverseBreadthFirst(callable|AstVisitorInterface $visitor, bool $includeRoot = true): void
    {
        $this->root->traverseBreadthFirst($visitor, $includeRoot);
    }

    /**
     * Traverses only nonterminal nodes in the document tree.
     *
     * @param callable(AstNode, int): (AstTraversalAction|bool|null)|AstVisitorInterface $visitor
     */
    public function traverseNonterminals(callable|AstVisitorInterface $visitor, bool $includeRoot = true): void
    {
        $this->root->traverseNonterminals($visitor, $includeRoot);
    }

    /**
     * Traverses only leaf nodes in the document tree.
     *
     * @param callable(AstNode, int): (AstTraversalAction|bool|null)|AstVisitorInterface $visitor
     */
    public function traverseLeaves(callable|AstVisitorInterface $visitor, bool $includeRoot = true): void
    {
        $this->root->traverseLeaves($visitor, $includeRoot);
    }

    /**
     * Traverses only lake nodes in the document tree.
     *
     * @param callable(AstNode, int): (AstTraversalAction|bool|null)|AstVisitorInterface $visitor
     */
    public function traverseLakeNodes(callable|AstVisitorInterface $visitor, bool $includeRoot = true): void
    {
        $this->root->traverseLakeNodes($visitor, $includeRoot);
    }

    /**
     * Traverses the tree with a typed visitor.
     */
    public function accept(AstNodeVisitorInterface $visitor): void
    {
        $this->traverseDepthFirst(function (AstNode $node, int $depth) use ($visitor) {
            $action = $node->accept($visitor, $depth);

            if ($action === AstTraversalAction::Stop) {
                return AstTraversalAction::Stop;
            }

            return $action;
        });
    }

    /**
     * Renders the document with source preservation.
     */
    public function print(?PrintPolicy $policy = null): string
    {
        return (new SourcePreservingPrinter($policy ?? new PrintPolicy()))->print($this->root);
    }

    /**
     * Re-parses the rendered source to validate the current tree.
     */
    public function validatePrintedSource(): ParseResult
    {
        return $this->grammar->parse($this->print(), $this->root->name());
    }

    /**
     * Validates the document by re-parsing the rendered source.
     */
    public function validate(): ParseResult
    {
        return $this->validatePrintedSource();
    }

    /**
     * Returns whether the node matches the current selector step.
     */
    private function matchesStep(AstNode $node, AstSelectorStep $step): bool
    {
        if ($node->name() !== $step->name()) {
            return false;
        }

        foreach ($step->attributes() as $name => $value) {
            if ($node->attribute($name) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function deduplicate(array $nodes): array
    {
        $seen = [];
        $result = [];

        foreach ($nodes as $node) {
            $key = spl_object_id($node);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $node;
        }

        return $result;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function applyPseudo(array $nodes, AstSelectorStep $step): array
    {
        return match ($step->pseudo()) {
            null => $nodes,
            'first' => $this->applyGroupedPseudo($nodes, static fn (array $group): array => $group === [] ? [] : [$group[0]]),
            'last' => $this->applyGroupedPseudo($nodes, static fn (array $group): array => $group === [] ? [] : [$group[array_key_last($group)]]),
            'nth-child' => $this->applyGroupedPseudo($nodes, fn (array $group): array => isset($group[$step->pseudoArgument() - 1]) ? [$group[$step->pseudoArgument() - 1]] : []),
            default => $nodes,
        };
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function applyGroupedPseudo(array $nodes, callable $selector): array
    {
        $groups = [];

        foreach ($nodes as $node) {
            $parentId = $node->parent() === null ? 'root' : (string) spl_object_id($node->parent());
            $groups[$parentId][] = $node;
        }

        $result = [];
        foreach ($groups as $group) {
            array_push($result, ...$selector($group));
        }

        return $result;
    }
}
