# AST

This is the compact overview of PHPeg's AST model.

For the full reference, use the dedicated pages:

- [AST model](ast/model.md)
- [Walking and visiting](ast/walking.md)
- [AST query language](ast/query.md)
- [Source-preserving mutation](ast/mutation.md)
- [Source-preserving printing](ast/printing.md)

## What PHPeg Returns

- `Grammar::parse()` returns a `ParseResult`
- `Grammar::parseDocument()` returns a `ParsedDocument`
- successful document parses produce a mutable `AstNode` tree rooted at the grammar start rule

## Core Ideas

- AST nodes are source-aware and can be queried or mutated in place
- selector queries work on both `ParsedDocument` and `AstNode`
- traversal and visitor APIs cover both the AST and the grammar expression graph
- source-preserving printing keeps untouched source intact
- validation is reparsing-based

## When To Read More

- want the node API and semantic attributes? read [AST model](ast/model.md)
- want traversal and visitor patterns? read [Walking and visiting](ast/walking.md)
- want selector syntax? read [AST query language](ast/query.md)
- want editing and validation? read [Source-preserving mutation](ast/mutation.md)
- want print behavior? read [Source-preserving printing](ast/printing.md)
