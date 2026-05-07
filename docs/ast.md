# AST

This page is the canonical reference for PHPeg's AST model, selector language, source-preserving edits, and printing flow.

## What The Runtime Returns

- `Grammar::parse()` returns a `ParseResult`
- `Grammar::parseDocument()` returns a `ParsedDocument`
- successful parses produce an `AstNode` tree rooted at the grammar start rule

## Core AST Nodes

`AstNode` is the mutable, source-aware node type used throughout the library.

Common methods:

- `name()`
- `text()`
- `originalText()`
- `startOffset()`
- `endOffset()`
- `children()`
- `parent()`
- `firstChild(string $name)`
- `childrenByName(string $name)`
- `query(string $selector)`
- `attribute(string $name)`

Node state:

- `isOriginal()`
- `isModified()`
- `isInserted()`
- `isRemoved()`
- `document()`
- `canContainChildren()`

Source-preserving editing:

- `prependNode()`
- `appendNode()`
- `before()`
- `after()`
- `replaceWith()`
- `remove()`

Source-preserving internals:

- `originalChildren()`
- `slotNodes()`
- `insertionsBefore()`
- `insertionsAfter()`

## Semantic Attributes

`attribute()` returns explicit attributes first, then derived values.

- `text` returns the trimmed node text
- `type` returns the node name
- `name` is derived from common child names such as `Identifier`, `Name`, or `Key`
- `value` is derived from common child names such as `Value`, `String`, `Number`, `Literal`, `Path`, `Url`, or `ValueList`
- custom nodes can also expose explicit attributes, such as the `kind="lake"` marker on lake nodes

## Querying The AST

PHPeg documents expose a selector language for finding AST nodes.

### Entry Points

Use `query()` on either a `ParsedDocument` or an `AstNode`.

```php
$document->query('Block > Statement');
$node->query('Directive');
```

`ParsedDocument::query()` searches from the document root by default.
`AstNode::query()` searches from the current node, while still using the same selector language.

### Example

Use a small grammar to see how node names and combinators work together.

```peg
Program <- Block EOF
Block <- "block" Identifier "{" Item* "}"
Item <- Directive / ~
Directive <- Identifier Value? ";"
Identifier <- [A-Za-z_][A-Za-z0-9_]*
Value <- Number / String
Number <- [0-9]+
String <- '"' [^"]* '"'
```

Example input:

```text
block server {
  listen 80;
  server_name example.com;
  # ignored text
}
```

Queries:

```php
$document->query('Block');
$document->query('Block Directive');
$document->query('Block > Directive');
$document->query('Block > Directive:first');
$document->query('Block > Directive:last');
$document->query('Block > Directive:nth-child(2)');
```

What each selector means:

- `Block`
  - matches the `Block` node itself
  - the node name is `Block`
- `Block Directive`
  - matches any `Directive` descendant under `Block`
  - descendant selectors use whitespace
- `Block > Directive`
  - matches only direct `Directive` children of `Block`
  - direct child selectors use `>`
- `Block > Directive:first`
  - returns the first direct `Directive` child under each parent `Block`
- `Block > Directive:last`
  - returns the last direct `Directive` child under each parent `Block`
- `Block > Directive:nth-child(2)`
  - returns the second direct `Directive` child under each parent `Block`

### Query Results

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

### Limitations

- No wildcard selectors.
- No `:not()` or other advanced CSS selectors.
- Pseudo selectors are simple and grouped by parent only.

## Source-Preserving Editing

PHPeg supports source-preserving tree mutation on parsed documents.

The mutation workflow is:

1. parse source with `parseDocument()`
2. query the nodes you want to change
3. replace, insert, or remove AST nodes
4. render the edited tree back to text with `print()`
5. validate the result by reparsing the printed source with the same grammar

This is the core loop for config editors, refactoring tools, and scripted migrations.

### Supported Operations

On nodes:

- `prependNode()`
- `appendNode()`
- `before()`
- `after()`
- `replaceWith()`
- `remove()`

On collections:

- `appendNode()`
- `prependNode()`
- `each()`

### Replacing Nodes

`replaceWith()` is the most common mutation operation.
It swaps an existing node for a new one while keeping the tree attached to the same parsed document.

For example, this is how the nginx config example updates the `worker_processes` value in place:

```php
use EmanueleCoppola\PHPeg\Ast\AstNodeFactory;
use EmanueleCoppola\PHPeg\Loader\CleanPeg\CleanPegGrammarLoader;

$grammar = (new CleanPegGrammarLoader())->fromFile('nginx-config-grammar.cleanpeg', startRule: 'NginxConfig');
$document = $grammar->parseDocument(file_get_contents('nginx.conf'));
$factory = new AstNodeFactory();

$document->query('Directive[name="worker_processes"] Number[text="2"]')->first()?->replaceWith(
    $factory->token('Number', '4')
);

echo $document->print();
```

The important part is that you mutate the AST, then ask the document to render the modified tree back into a string.
The printer keeps the untouched parts of the original source as-is and only rewrites the parts that changed.

### Generic Factory

Use `AstNodeFactory` for inserted or replacement nodes:

```php
$factory = new AstNodeFactory();
$factory->node('Statement', text: '    print "inserted"' . "\n");
$factory->token('Identifier', 'renamed');
```

Inserted nodes should usually carry explicit source text in this first version, because the printer needs text to render them back into the source.

## Source-Preserving Printing

`ParsedDocument` keeps the original source string and prints a modified tree back out with `print()`.

Practical flow:

1. parse the source with `parseDocument()`
2. query nodes with selectors
3. apply mutations with node or collection methods
4. call `print()`
5. optionally call `validatePrintedSource()` to reparse the result

Typical edit pattern:

```php
$document = $grammar->parseDocument($source);
$document->query('Directive[name="worker_processes"] Number[text="2"]')->first()?->replaceWith(
    $factory->token('Number', '4')
);

$printed = $document->print();
$validation = $document->validatePrintedSource();
```

This is a source-preserving edit loop: the tree is changed, the document is rendered back to a string, and the same grammar can then parse the result again.

## Validation

PHPeg currently uses reparsing as its mutation validation strategy:

```php
$result = $document->validate();
```

`validate()` and `validatePrintedSource()` both reparse the rendered source with the original grammar.
If the parse succeeds, the mutation is considered structurally valid for that grammar.

That gives you a simple and deterministic check after an edit:

1. mutate the tree
2. print it
3. reparse it
4. inspect the returned `ParseResult`

## Limitations

- Full grammar-aware mutation validation is not yet implemented.
- Inserting into leaf/token-like nodes is rejected.
- Inserted nodes without explicit render text have limited printer support.
