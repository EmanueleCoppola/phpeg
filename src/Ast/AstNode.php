<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Ast;

use EmanueleCoppola\PHPeg\Document\ParsedDocument;
use EmanueleCoppola\PHPeg\Error\AstMutationError;
use EmanueleCoppola\PHPeg\Mutation\InsertPosition;
use EmanueleCoppola\PHPeg\Parser\InputBuffer;

/**
 * Mutable source-aware AST node used for querying and source-preserving edits.
 */
class AstNode
{
    /**
     * @var list<AstNode>
     */
    private array $children;

    /**
     * @var list<AstNode>
     */
    private array $originalChildren;

    private bool $originalStructureMirrorsChildren = false;

    /**
     * @var array<int, AstNode|null>
     */
    private array $slotNodes = [];

    private ?AstNode $parent = null;

    private ?ParsedDocument $document = null;

    /**
     * @var array<string, string>
     */
    private array $attributes;

    private bool $modified = false;

    private bool $inserted = false;

    private bool $removed = false;

    private ?string $originalText;

    private ?string $renderText;

    private ?InputBuffer $sourceBuffer;

    /**
     * @var array<int, list<AstNode>>
     */
    private array $insertBefore = [];

    /**
     * @var array<int, list<AstNode>>
     */
    private array $insertAfter = [];

    /**
     * Creates a detached clone for source-preserving mutations.
     */
    public function __clone()
    {
        $this->parent = null;
        $this->document = null;
        $clonedChildren = [];

        foreach ($this->children as $child) {
            $clonedChild = clone $child;
            $clonedChild->parent = $this;
            $clonedChildren[] = $clonedChild;
        }

        $this->children = $clonedChildren;
        $this->originalChildren = [];
        $this->originalStructureMirrorsChildren = false;
        $this->slotNodes = [];
        $this->insertBefore = [];
        $this->insertAfter = [];
        $this->inserted = true;
        $this->removed = false;
    }

    /**
     * @param list<AstNode> $children
     * @param array<string, string> $attributes
     */
    public function __construct(
        private readonly string $name,
        ?string $text,
        private readonly int $startOffset,
        private readonly int $endOffset,
        array $children = [],
        array $attributes = [],
        bool $isOriginal = true,
        ?string $renderText = null,
        ?InputBuffer $sourceBuffer = null,
    ) {
        $this->children = array_values($children);
        $this->originalChildren = [];
        $this->originalStructureMirrorsChildren = $isOriginal;
        $this->attributes = $attributes;
        $this->originalText = $text;
        $this->sourceBuffer = $sourceBuffer;
        $this->renderText = $renderText ?? (!$isOriginal ? $text : null);
        $this->inserted = !$isOriginal;

        foreach ($this->children as $child) {
            $child->parent = $this;
        }
    }

    /**
     * Returns the rule name that created this node.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the exact original or rendered text for this node.
     */
    public function text(): string
    {
        if ($this->renderText !== null) {
            return $this->renderText;
        }

        return $this->materializedOriginalText();
    }

    /**
     * Returns the original start offset in the source input.
     */
    public function startOffset(): int
    {
        return $this->startOffset;
    }

    /**
     * Returns the original end offset in the source input.
     */
    public function endOffset(): int
    {
        return $this->endOffset;
    }

    /**
     * @return list<AstNode>
     */
    public function children(): array
    {
        return array_values(array_filter($this->children, static fn (AstNode $child): bool => !$child->removed));
    }

    /**
     * Returns the parent node, or null for the root node.
     */
    public function parent(): ?AstNode
    {
        return $this->parent;
    }

    /**
     * Returns whether this node comes from the original parsed source.
     */
    public function isOriginal(): bool
    {
        return !$this->inserted;
    }

    /**
     * Returns whether this node or its child list has been modified.
     */
    public function isModified(): bool
    {
        return $this->modified;
    }

    /**
     * Returns whether this node was inserted after parsing.
     */
    public function isInserted(): bool
    {
        return $this->inserted;
    }

    /**
     * Returns whether this node has been removed from the tree.
     */
    public function isRemoved(): bool
    {
        return $this->removed;
    }

    /**
     * Returns the owning parsed document when attached.
     */
    public function document(): ?ParsedDocument
    {
        return $this->document;
    }

    /**
     * Returns the first child with the provided rule name, or null.
     */
    public function firstChild(string $name): ?AstNode
    {
        foreach ($this->children() as $child) {
            if ($child->name() === $name) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    public function childrenByName(string $name): array
    {
        return array_values(
            array_filter(
                $this->children(),
                static fn (AstNode $child): bool => $child->name() === $name,
            ),
        );
    }

    /**
     * Traverses the subtree in depth-first pre-order.
     *
     * The visitor may return one of:
     * - `AstTraversalAction::Continue` to keep visiting descendants
     * - `AstTraversalAction::SkipChildren` to skip the current node's descendants
     * - `AstTraversalAction::Stop` to stop traversal entirely
     * - `true` or `null` as shorthand for continue
     * - `false` as shorthand for stop
     *
     * @param callable(AstNode, int): (AstTraversalAction|bool|null)|AstVisitorInterface $visitor
     */
    public function traverseDepthFirst(callable|AstVisitorInterface $visitor, bool $includeSelf = true): void
    {
        $visitor = $this->normalizeVisitor($visitor);

        if ($includeSelf) {
            $action = $this->normalizeTraversalAction($visitor($this, 0));
            if ($action === AstTraversalAction::Stop) {
                return;
            }

            if ($action !== AstTraversalAction::SkipChildren) {
                $this->traverseDepthFirstChildren($visitor, 1);
            }

            return;
        }

        $this->traverseDepthFirstChildren($visitor, 0);
    }

    /**
     * Traverses the subtree in breadth-first order.
     *
     * The visitor follows the same return contract as traverseDepthFirst().
     *
     * @param callable(AstNode, int): (AstTraversalAction|bool|null)|AstVisitorInterface $visitor
     */
    public function traverseBreadthFirst(callable|AstVisitorInterface $visitor, bool $includeSelf = true): void
    {
        $visitor = $this->normalizeVisitor($visitor);
        $queue = [];

        if ($includeSelf) {
            $queue[] = [$this, 0];
        } else {
            foreach ($this->children() as $child) {
                $queue[] = [$child, 0];
            }
        }

        while ($queue !== []) {
            [$node, $depth] = array_shift($queue);
            $action = $this->normalizeTraversalAction($visitor($node, $depth));

            if ($action === AstTraversalAction::Stop) {
                return;
            }

            if ($action === AstTraversalAction::SkipChildren) {
                continue;
            }

            foreach ($node->children() as $child) {
                $queue[] = [$child, $depth + 1];
            }
        }
    }

    /**
     * Traverses only nonterminal nodes, meaning nodes that have children.
     *
     * @param callable(AstNode, int): (AstTraversalAction|bool|null)|AstVisitorInterface $visitor
     */
    public function traverseNonterminals(callable|AstVisitorInterface $visitor, bool $includeSelf = true): void
    {
        $this->traverseDepthFirst(
            function (AstNode $node, int $depth) use ($visitor): AstTraversalAction|bool|null {
                if ($node->children() === []) {
                    return AstTraversalAction::Continue;
                }

                $normalized = $this->normalizeVisitor($visitor);
                return $normalized($node, $depth);
            },
            $includeSelf,
        );
    }

    /**
     * Traverses only leaf nodes.
     *
     * @param callable(AstNode, int): (AstTraversalAction|bool|null)|AstVisitorInterface $visitor
     */
    public function traverseLeaves(callable|AstVisitorInterface $visitor, bool $includeSelf = true): void
    {
        $this->traverseDepthFirst(
            function (AstNode $node, int $depth) use ($visitor): AstTraversalAction|bool|null {
                if ($node->children() !== []) {
                    return AstTraversalAction::Continue;
                }

                $normalized = $this->normalizeVisitor($visitor);
                return $normalized($node, $depth);
            },
            $includeSelf,
        );
    }

    /**
     * Traverses only lake nodes.
     *
     * @param callable(AstNode, int): (AstTraversalAction|bool|null)|AstVisitorInterface $visitor
     */
    public function traverseLakeNodes(callable|AstVisitorInterface $visitor, bool $includeSelf = true): void
    {
        $this->traverseDepthFirst(
            function (AstNode $node, int $depth) use ($visitor): AstTraversalAction|bool|null {
                if (!$node->isLake()) {
                    return AstTraversalAction::Continue;
                }

                $normalized = $this->normalizeVisitor($visitor);
                return $normalized($node, $depth);
            },
            $includeSelf,
        );
    }

    /**
     * Dispatches this node to a typed visitor.
     */
    public function accept(AstNodeVisitorInterface $visitor, int $depth = 0)
    {
        if ($this->isLake()) {
            return $visitor->visitLake($this, $depth);
        }

        if ($this->children() === []) {
            return $visitor->visitLeaf($this, $depth);
        }

        return $visitor->visitNonterminal($this, $depth);
    }

    /**
     * Queries descendants rooted at this node using the selector API.
     */
    public function query(string $selector): AstNodeCollection
    {
        if ($this->document === null) {
            throw new AstMutationError('Cannot query a detached AST node.');
        }

        return $this->document->query($selector, $this);
    }

    /**
     * Returns a semantic attribute value when available.
     */
    public function attribute(string $name): ?string
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        return match ($name) {
            'text' => trim($this->text()),
            'type' => $this->name(),
            'name' => $this->derivedNameAttribute(),
            'value' => $this->derivedValueAttribute(),
            default => null,
        };
    }

    /**
     * Returns the explicit attribute map stored on the node.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * Returns whether the node represents a lake capture.
     */
    public function isLake(): bool
    {
        return ($this->attributes['kind'] ?? null) === 'lake';
    }

    /**
     * Adds a node as the first logical child.
     */
    public function prependNode(AstNode $node): self
    {
        return $this->insertChild($node, InsertPosition::Prepend);
    }

    /**
     * Adds a node as the last logical child.
     */
    public function appendNode(AstNode $node): self
    {
        return $this->insertChild($node, InsertPosition::Append);
    }

    /**
     * Inserts a node before this node.
     */
    public function before(AstNode $node): self
    {
        $parent = $this->parent ?? throw new AstMutationError('Cannot insert before the root node.');
        $parent->insertRelativeToChild($this, $node, InsertPosition::Before);

        return $this;
    }

    /**
     * Inserts a node after this node.
     */
    public function after(AstNode $node): self
    {
        $parent = $this->parent ?? throw new AstMutationError('Cannot insert after the root node.');
        $parent->insertRelativeToChild($this, $node, InsertPosition::After);

        return $this;
    }

    /**
     * Replaces this node with another node.
     */
    public function replaceWith(AstNode $node): self
    {
        $parent = $this->parent ?? throw new AstMutationError('Cannot replace the root node.');
        $parent->replaceChild($this, $node);

        return $this;
    }

    /**
     * Removes this node from the tree.
     */
    public function remove(): self
    {
        $parent = $this->parent ?? throw new AstMutationError('Cannot remove the root node.');
        $parent->removeChild($this);

        return $this;
    }

    /**
     * Returns whether the node can accept appended/prepended children.
     */
    public function canContainChildren(): bool
    {
        if ($this->removed) {
            return false;
        }

        if ($this->children !== [] || $this->currentOriginalChildren() !== []) {
            return true;
        }

        $text = $this->materializedOriginalText();

        return str_contains($text, '{') && str_contains($text, '}');
    }

    /**
     * Attaches this node and its descendants to a parsed document.
     */
    public function attachDocument(ParsedDocument $document): void
    {
        $this->document = $document;

        foreach ($this->children as $child) {
            $child->parent = $this;
            $child->attachDocument($document);
        }
    }

    /**
     * Returns the original text slice captured during parsing.
     */
    public function originalText(): string
    {
        return $this->materializedOriginalText();
    }

    /**
     * @return list<AstNode>
     */
    public function originalChildren(): array
    {
        return $this->currentOriginalChildren();
    }

    /**
     * @return array<int, AstNode|null>
     */
    public function slotNodes(): array
    {
        return $this->currentSlotNodes();
    }

    /**
     * @return array<int, list<AstNode>>
     */
    public function insertionsBefore(): array
    {
        return $this->insertBefore;
    }

    /**
     * @return array<int, list<AstNode>>
     */
    public function insertionsAfter(): array
    {
        return $this->insertAfter;
    }

    /**
     * Stores an explicit renderable text representation for inserted/replaced nodes.
     */
    public function setRenderText(string $text): void
    {
        $this->renderText = $text;
        $this->markModified();
    }

    /**
     * @param array<string, string> $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
        $this->markModified();
    }

    /**
     * @return list<AstNode>
     */
    public function descendantsAndSelf(): array
    {
        $nodes = [$this];

        foreach ($this->children() as $child) {
            array_push($nodes, ...$child->descendantsAndSelf());
        }

        return $nodes;
    }

    /**
     * @return list<AstNode>
     */
    public function directChildren(): array
    {
        return $this->children();
    }

    /**
     * Inserts a child node relative to the current node ordering.
     */
    private function insertChild(AstNode $node, InsertPosition $position): self
    {
        if (!$this->canContainChildren()) {
            throw new AstMutationError(sprintf('Cannot insert children into leaf node "%s".', $this->name));
        }

        $this->ensureOriginalStructureTracking();
        $originalChildren = $this->currentOriginalChildren();

        $node->inserted = true;
        $node->parent = $this;
        if ($this->document !== null) {
            $node->attachDocument($this->document);
        }

        $this->children = match ($position) {
            InsertPosition::Prepend => [...[$node], ...$this->children()],
            InsertPosition::Append => [...$this->children(), $node],
            default => $this->children,
        };

        if ($originalChildren === []) {
            $this->insertAfter[-1] ??= [];
            $this->insertAfter[-1][] = $node;
        } elseif ($position === InsertPosition::Prepend) {
            $this->insertBefore[0] ??= [];
            $this->insertBefore[0][] = $node;
        } else {
            $lastSlot = count($originalChildren) - 1;
            $this->insertAfter[$lastSlot] ??= [];
            $this->insertAfter[$lastSlot][] = $node;
        }

        $this->markModified();

        return $this;
    }

    /**
     * Inserts a child before or after another child node.
     */
    private function insertRelativeToChild(AstNode $target, AstNode $node, InsertPosition $position): void
    {
        $this->ensureOriginalStructureTracking();
        $index = $this->findChildIndex($target);

        $node->inserted = true;
        $node->parent = $this;
        if ($this->document !== null) {
            $node->attachDocument($this->document);
        }

        array_splice($this->children, $position === InsertPosition::Before ? $index : $index + 1, 0, [$node]);

        $originalIndex = $this->findOriginalChildIndex($target);
        if ($originalIndex === null) {
            $originalIndex = max(0, $index - 1);
        }

        if ($position === InsertPosition::Before) {
            $this->insertBefore[$originalIndex] ??= [];
            $this->insertBefore[$originalIndex][] = $node;
        } else {
            $this->insertAfter[$originalIndex] ??= [];
            $this->insertAfter[$originalIndex][] = $node;
        }

        $this->markModified();
    }

    /**
     * Replaces an existing child with a new node.
     */
    private function replaceChild(AstNode $target, AstNode $replacement): void
    {
        $this->ensureOriginalStructureTracking();
        $index = $this->findChildIndex($target);
        $slotIndex = $this->findOriginalChildIndex($target);

        $replacement->inserted = true;
        $replacement->parent = $this;
        if ($this->document !== null) {
            $replacement->attachDocument($this->document);
        }

        $this->children[$index] = $replacement;
        $target->removed = true;
        $target->parent = null;

        if ($slotIndex !== null) {
            $this->slotNodes[$slotIndex] = $replacement;
        }

        $this->markModified();
    }

    /**
     * Removes a child node from the current node.
     */
    private function removeChild(AstNode $target): void
    {
        $this->ensureOriginalStructureTracking();
        $index = $this->findChildIndex($target);
        array_splice($this->children, $index, 1);
        $target->removed = true;
        $target->parent = null;
        if (($slotIndex = $this->findOriginalChildIndex($target)) !== null) {
            $this->slotNodes[$slotIndex] = null;
        }
        $this->markModified();
    }

    /**
     * Finds the index of a child node in the current children list.
     */
    private function findChildIndex(AstNode $target): int
    {
        foreach ($this->children as $index => $child) {
            if ($child === $target) {
                return $index;
            }
        }

        throw new AstMutationError('Target node is not a child of the expected parent.');
    }

    /**
     * Finds the index of a child in the original child snapshot.
     */
    private function findOriginalChildIndex(AstNode $target): ?int
    {
        foreach ($this->currentOriginalChildren() as $index => $child) {
            if ($child === $target) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Marks the document as modified.
     */
    private function markModified(): void
    {
        $this->modified = true;
        if ($this->document !== null) {
            $this->document->markModified();
        }
        if ($this->parent !== null && !$this->parent->modified) {
            $this->parent->markModified();
        }
    }

    /**
     * Derives a semantic name from common child node patterns.
     */
    private function derivedNameAttribute(): ?string
    {
        foreach (['Identifier', 'Name', 'Key'] as $childName) {
            $child = $this->firstChild($childName);
            if ($child !== null) {
                return trim($child->text(), "\"' \t\r\n");
            }
        }

        return null;
    }

    /**
     * Derives a semantic value from common child node patterns.
     */
    private function derivedValueAttribute(): ?string
    {
        foreach (['Value', 'String', 'Number', 'Literal', 'Path', 'Url', 'ValueList'] as $childName) {
            $child = $this->firstChild($childName);
            if ($child !== null) {
        return trim($child->text(), "\"' \t\r\n");
            }
        }

        return null;
    }

    /**
     * @param callable(AstNode, int): (AstTraversalAction|bool|null)|AstVisitorInterface $visitor
     * @return callable(AstNode, int): (AstTraversalAction|bool|null)
     */
    private function normalizeVisitor(callable|AstVisitorInterface $visitor): callable
    {
        if ($visitor instanceof AstVisitorInterface) {
            return $visitor->visit(...);
        }

        return $visitor;
    }

    /**
     * @param callable(AstNode, int): (AstTraversalAction|bool|null) $visitor
     */
    private function traverseDepthFirstChildren(callable $visitor, int $depth): bool
    {
        foreach ($this->children() as $child) {
            $action = $this->normalizeTraversalAction($visitor($child, $depth));
            if ($action === AstTraversalAction::Stop) {
                return false;
            }

            if ($action !== AstTraversalAction::SkipChildren) {
                if (!$child->traverseDepthFirstChildren($visitor, $depth + 1)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param AstTraversalAction|bool|null $action
     */
    private function normalizeTraversalAction(AstTraversalAction|bool|null $action): AstTraversalAction
    {
        if ($action instanceof AstTraversalAction) {
            return $action;
        }

        return $action === false ? AstTraversalAction::Stop : AstTraversalAction::Continue;
    }

    /**
     * Returns the original child snapshot for source-preserving operations.
     *
     * @return list<AstNode>
     */
    private function currentOriginalChildren(): array
    {
        if ($this->originalStructureMirrorsChildren) {
            return $this->children;
        }

        return $this->originalChildren;
    }

    /**
     * Returns the current slot map without forcing source-preserving setup.
     *
     * @return array<int, AstNode|null>
     */
    private function currentSlotNodes(): array
    {
        if (!$this->originalStructureMirrorsChildren) {
            return $this->slotNodes;
        }

        $slotNodes = [];
        foreach ($this->children as $index => $child) {
            $slotNodes[$index] = $child;
        }

        return $slotNodes;
    }

    /**
     * Captures the original child layout before the node is mutated.
     */
    private function ensureOriginalStructureTracking(): void
    {
        if (!$this->originalStructureMirrorsChildren) {
            return;
        }

        $this->originalChildren = $this->children;
        $this->slotNodes = [];
        foreach ($this->originalChildren as $index => $child) {
            $this->slotNodes[$index] = $child;
        }

        $this->originalStructureMirrorsChildren = false;
    }

    /**
     * Returns the original source text, loading it lazily when configured.
     */
    private function materializedOriginalText(): string
    {
        if ($this->originalText !== null) {
            return $this->originalText;
        }

        if ($this->sourceBuffer === null) {
            return '';
        }

        $this->originalText = $this->sourceBuffer->slice($this->startOffset, $this->endOffset);

        return $this->originalText;
    }
}
