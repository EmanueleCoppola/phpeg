# Source-Preserving Printing

`ParsedDocument::print()` renders the current tree back to text while preserving untouched source as-is.

That makes the printer suitable for:

- config editors
- scripted migrations
- refactoring tools
- AST-driven source transformations

## Behavior

The printer keeps the original source for nodes that were not changed.
When a node is inserted or replaced, the printer uses the node's render text.

## Typical Flow

1. parse source with `parseDocument()`
2. mutate the AST
3. call `print()`
4. optionally call `validatePrintedSource()` or `validate()`

## Validation

Validation is reparsing-based.

```php
$printed = $document->print();
$validation = $document->validatePrintedSource();
```

If the reparse succeeds, the printed source is structurally valid for the original grammar.

## Notes

- `print()` returns a string
- `validatePrintedSource()` returns a `ParseResult`
- `validate()` is a convenience alias for `validatePrintedSource()`
