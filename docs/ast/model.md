# AST Model

PHPeg parses source into a mutable, source-aware AST rooted in an `AstNode`.
Parsed documents wrap that tree in a `ParsedDocument` so you can query, mutate, and print it back with source preservation.

## Parse Entry Points

- `Grammar::parse()` returns a `ParseResult`
- `Grammar::parseDocument()` returns a `ParsedDocument`
- successful document parses expose the root `AstNode`

## Parsed Document

`ParsedDocument` owns the parsed source, the grammar, and the root AST node.

Common methods:

- `root()`
- `source()`
- `isModified()`
- `markModified()`
- `query(string $selector, ?AstNode $scope = null)`
- `print(?PrintPolicy $policy = null)`
- `validatePrintedSource()`
- `validate()`

`validate()` is an alias of `validatePrintedSource()`.

## AstNode

`AstNode` represents a rule match, terminal, or lake node.

Core accessors:

- `name()`
- `text()`
- `originalText()`
- `startOffset()`
- `endOffset()`
- `children()`
- `parent()`
- `document()`

Semantic helpers:

- `attribute(string $name)`
- `attributes()`
- `isLake()`
- `isOriginal()`
- `isModified()`
- `isInserted()`
- `isRemoved()`
- `canContainChildren()`

Navigation helpers:

- `firstChild(string $name)`
- `childrenByName(string $name)`
- `descendantsAndSelf()`
- `directChildren()`
- `query(string $selector)`

Source-preserving edit helpers:

- `prependNode()`
- `appendNode()`
- `before()`
- `after()`
- `replaceWith()`
- `remove()`

Source-preserving internals that are useful to know when reading the implementation:

- `originalChildren()`
- `slotNodes()`
- `insertionsBefore()`
- `insertionsAfter()`

## Semantic Attributes

`attribute()` returns explicit attributes first, then derived values.

Built-in semantic keys:

- `text` returns the trimmed node text
- `type` returns the node name
- `name` is derived from common child names such as `Identifier`, `Name`, or `Key`
- `value` is derived from common child names such as `Value`, `String`, `Number`, `Literal`, `Path`, `Url`, or `ValueList`

Explicit attributes still win over derived values, so grammar-specific metadata remains authoritative.

## Collections

`query()` returns an `AstNodeCollection`.

Collection methods:

- `count()`
- `isEmpty()`
- `first()`
- `last()`
- `all()`
- `each(callable $callback)`
- `appendNode(AstNode $node)`
- `prependNode(AstNode $node)`

`appendNode()` and `prependNode()` clone the provided node for each target in the collection.
