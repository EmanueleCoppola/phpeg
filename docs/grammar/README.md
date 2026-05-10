# Grammar Reference

This directory documents the three supported grammar definition styles in PHPeg.

## When To Use What

- Use the **Fluent PHP Builder** when the grammar lives in application code and you want to build it programmatically in PHP.
- Use the **Classic PEG Loader** when you already have PEG grammars or want the most explicit, traditional notation.
- Use the **CleanPeg Loader** when you want a compact grammar format with less boilerplate and are fine with loader conveniences.

Practical differences:

- **Fluent PHP Builder**
  - you write PHP code, not grammar text
  - you get full control over expressions and composition
  - nothing is injected automatically by a loader
- **Classic PEG Loader**
  - `EOF` is not built in, so you usually define it yourself
  - whitespace is not skipped automatically
  - you get the closest fit to traditional PEG notation
  - case sensitivity can be scoped with `@insensitive` / `@sensitive` and overridden on terminals with `i`
- **CleanPeg Loader**
  - `EOF` is built in
  - whitespace is skipped automatically by default
  - the syntax is shorter, but the loader adds convenience behavior
  - case sensitivity can be scoped with `@insensitive` / `@sensitive` and overridden on terminals with `i`

## Pages

- [Fluent PHP Builder](fluent-php-builder.md)
- [CleanPeg Loader](clean-peg-loader.md)
- [Classic PEG Loader](classic-peg-loader.md)

## Shared Runtime Model

All three styles compile to the same runtime model:

- `Grammar` stores named `Rule` objects plus a start rule and optional named lake profiles.
- `Grammar::parse()` returns a `ParseResult`.
- `Grammar::parseDocument()` returns a `ParsedDocument` for source-preserving editing.
- Parsed rules produce `AstNode` trees with rule names, offsets, text, children, attributes, and mutation support.

The style-specific pages below focus on syntax. For AST querying, mutation, and source-preserving printing, see [AST](../ast.md).

Lake symbols let a grammar describe the interesting islands inside a larger document, while water symbols mark reusable background rules such as whitespace, comments, or strings.
If you want to read more about lake and water symbols, see [Lake symbols](../lake-symbols.md).
