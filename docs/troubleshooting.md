# Troubleshooting

This page collects the most common production issues and the quickest way to diagnose them.

## Grammar Does Not Load

Check:

- the file path exists
- the grammar format matches the file extension or the explicit `--grammar-format`
- the grammar syntax matches the selected loader

If the loader cannot infer the format, pass `--grammar-format=peg` or `--grammar-format=cleanpeg` explicitly.

## Parse Fails

Check:

- the start rule is correct
- the input file contains the expected structure
- the grammar is not over-skipping whitespace or water

For CLI work, inspect the error output first, then use `trace` and `step` when you need a parse walkthrough.

## Selector Does Not Match

Check:

- the node name matches the AST exactly
- the attribute filter uses the semantic attribute names you expect
- the selector uses `>` only when you want direct children

If you need a quick sanity check, query a broader selector first and then narrow it down.

## Printed Output Looks Wrong

Check:

- inserted nodes have render text
- the node you replaced is the correct one
- the grammar still parses the printed result

If needed, call `validatePrintedSource()` after printing and inspect the `ParseResult`.

## CLI Output Is Not JSON

Only the `parse` command writes JSON output by default.
The `step` command is interactive and prints a terminal view, not JSON.

## When To Use Each Grammar Style

- use the fluent builder when grammar definition lives in PHP code
- use CleanPeg when you want a compact grammar file with conveniences
- use classic PEG when you want a more traditional notation and explicit syntax
