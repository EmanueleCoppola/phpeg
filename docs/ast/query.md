# AST Query Language

PHPeg exposes a selector language for finding AST nodes inside a `ParsedDocument` or an `AstNode`.

## Entry Points

- `ParsedDocument::query(string $selector, ?AstNode $scope = null)`
- `AstNode::query(string $selector)`

`ParsedDocument::query()` searches from the document root by default.
`AstNode::query()` searches from the current node and uses the same selector syntax.

## Selector Shape

A selector is made of one or more steps.
Each step can include:

- a node name
- attribute filters
- a combinator
- an optional pseudo-selector

Examples:

```php
$document->query('Block');
$document->query('Block PrintStatement');
$document->query('Block > Directive:first');
$document->query('Identifier[text="nested"]');
```

## Combinators

- whitespace means descendant selection
- `>` means direct child selection

Examples:

- `Block PrintStatement` matches any `PrintStatement` descendant of `Block`
- `Block > Statement` matches only direct `Statement` children of `Block`

## Attribute Filters

Attribute filters use bracket syntax:

```text
Block[name="server"]
Identifier[text="nested"]
```

Filters compare against the node's semantic `attribute()` values.

That means `text`, `name`, `type`, and `value` work the same way as they do on the node API.

## Pseudo-Selectors

Supported pseudo-selectors:

- `:first`
- `:last`
- `:nth-child(N)`

Examples:

- `Block > Directive:first`
- `Block > Directive:last`
- `Block > Directive:nth-child(2)`

Pseudo-selectors are grouped by parent before the pseudo is applied.

## Results

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

## Limitations

- no wildcard selectors
- no `:not()`
- no advanced CSS selector groups
- invalid selectors throw `AstQueryError`

## Practical Example

```php
$document = $grammar->parseDocument($source);
$serverBlock = $document->query('Block[name="server"]')->first();

if ($serverBlock !== null) {
    $firstStatement = $serverBlock->query('Statement:first')->first();
}
```
