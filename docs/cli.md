# CLI

PHPeg ships with a command-line parser that loads `.peg` or `.cleanpeg` grammars and parses input files from the terminal.

The CLI can:

- load a grammar file
- parse an input file
- export the AST as JSON
- filter exported AST nodes with a selector
- optionally export the parse tree as Graphviz DOT

## Command

```bash
php bin/phpeg parse --grammar=path/to/grammar.cleanpeg --input=path/to/input.txt
```

Short aliases are available for input and output paths:

```bash
php bin/phpeg parse --grammar=path/to/grammar.cleanpeg -i path/to/input.txt -o result.json
```

## Basic Usage

Parse an input file with a grammar:

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt
```

By default, the command writes JSON to stdout:

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt > result.json
```

Write JSON directly to a file with `--output` or `-o`:

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt --output=result.json
```

Recommended form for quick inspection:

```bash
php bin/phpeg parse \
  --grammar=path/to/grammar.cleanpeg \
  --input=path/to/input.txt \
  --output=result.json
```

## Options

| Option | Required | Default | Description |
|---|---:|---|---|
| `--grammar=PATH` | Yes | - | Grammar file to load. Supports `.peg` and `.cleanpeg`. |
| `-i, --input=PATH` | Yes | - | Input file to parse. |
| `--grammar-format=FORMAT` | No | `auto` | Grammar format. One of `auto`, `cleanpeg`, `peg`. |
| `--start-rule=RULE` | No | grammar default | Start rule used for parsing. |
| `--query=SELECTOR` | No | root node | Filters exported AST nodes using the selector syntax. |
| `--json-style=STYLE` | No | `simple` | JSON output style. One of `full`, `simple`. |
| `-o, --output=PATH` | No | stdout | Writes JSON output to a file. |
| `--tree-format=FORMAT` | No | - | Parse tree export format. Currently only `dot` is supported. |
| `--tree-output=PATH` | Required with `--tree-format` | - | Path where the parse tree DOT file is written. |

## Required Input

The `parse` command requires two paths:

- `--grammar=PATH`
- `-i, --input=PATH`

Both paths may be relative or absolute.

Relative paths are resolved from the current working directory.

If either flag is missing, the command fails with a CLI error.

## Examples

### Parse with a selector

```bash
php bin/phpeg parse \
  --grammar=examples/nginx-config-edit/nginx-config-grammar.cleanpeg \
  --input=examples/nginx-config-edit/nginx-config.conf \
  --query='Block[name="server"]'
```

When a query is provided, the JSON output contains the matched nodes in the `matches` array.

### Export compact JSON

```bash
php bin/phpeg parse \
  --grammar=examples/nginx-config-edit/nginx-config-grammar.cleanpeg \
  --input=examples/nginx-config-edit/nginx-config.conf \
  --query='Block[name="server"]' \
  --json-style=simple
```

### Write JSON to a file

```bash
php bin/phpeg parse \
  --grammar=examples/nginx-config-edit/nginx-config-grammar.cleanpeg \
  --input=examples/nginx-config-edit/nginx-config.conf \
  --query='Block[name="server"]' \
  --json-style=simple \
  --output=server-node.json
```

### Pipe into `jq`

```bash
php bin/phpeg parse \
  --grammar=examples/nginx-config-edit/nginx-config-grammar.cleanpeg \
  --input=examples/nginx-config-edit/nginx-config.conf \
  --query='Block[name="server"]' \
  --json-style=simple \
  | jq '.matches[0] | {name, text, lake}'
```

### Export a parse tree as DOT

```bash
php bin/phpeg parse \
  --grammar=path/to/grammar.cleanpeg \
  --input=path/to/input.txt \
  --tree-format=dot \
  --tree-output=debug/output-tree.dot
```

Render the DOT file with Graphviz:

```bash
dot -Tsvg debug/output-tree.dot -o debug/output-tree.svg
```

## Grammar Format

By default, PHPeg detects the grammar format from the grammar file extension.

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt
```

`--grammar-format=auto` resolves formats as follows:

| Extension | Format |
|---|---|
| `.cleanpeg` | `cleanpeg` |
| `.peg` | `peg` |

You can force a grammar format explicitly:

```bash
php bin/phpeg parse \
  --grammar=grammar.txt \
  --grammar-format=cleanpeg \
  --input=input.txt
```

When `cleanpeg` or `peg` is provided explicitly, file extension inference is skipped.

## Querying the AST

`--query=SELECTOR` filters exported AST nodes using the same selector syntax as `ParsedDocument::query()`.

Examples:

```bash
--query='Block[name="server"]'
--query='Lake[kind="lake"]'
--query='Block > Directive:first'
```

If `--query` is omitted, the parsed root node is exported.

If `--query` is provided, the matched nodes are exported in the `matches` array.

See [AST](ast.md) for the full selector syntax and the rest of the AST model.

## JSON Output

The CLI writes JSON to stdout by default.

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt
```

Use `--output` or `-o` to write JSON to a file:

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt --output=result.json
```

The output file extension is ignored. The CLI always writes JSON to `--output`.

For example, all of these commands write JSON:

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt --output=result.json
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt --output=result.txt
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt --output=result.dump
```

## JSON Styles

PHPeg supports two JSON output styles:

| Style | Description |
|---|---|
| `full` | Detailed output with metadata, offsets, state flags, attributes, semantic fields, and children. |
| `simple` | Compact recursive tree with only `name`, `text`, `lake`, and `children`. |

The default style is `simple`. Pass `--json-style=full` when you need metadata, offsets, attributes, or semantic fields.

The `lake` field is `true` for nodes matched by lake/island parsing rules.

### `full`

`full` is the detailed schema and is useful when you need complete parse information.

It includes:

- grammar metadata
- input metadata
- parse offsets
- query metadata
- detailed node data

Each node contains:

- `name`
- `text`
- `originalText`
- offsets
- state flags
- `lake`
- raw `attributes`
- derived `semantic` fields
- recursive `children`

<details>
<summary>Example JSON</summary>

```json
{
  "success": true,
  "query": {
    "selector": "Block[name=\"server\"]",
    "count": 1
  },
  "matches": [
    {
      "name": "Block",
      "text": "server { ... }",
      "originalText": "server { ... }",
      "startOffset": 128,
      "endOffset": 512,
      "isOriginal": true,
      "isModified": false,
      "isInserted": false,
      "isRemoved": false,
      "lake": false,
      "attributes": {},
      "semantic": {
        "text": "server { ... }",
        "type": "Block",
        "name": "server",
        "value": null
      },
      "children": []
    }
  ]
}
```

</details>

### `simple`

`simple` is a compact schema intended for quick inspection, shell pipelines, and `jq`.

It keeps only:

- `name`
- `text`
- `lake`
- recursive `children`

Even in `simple` mode, the tree remains recursive, so nested nodes can still be inspected with tools like `jq`.

<details>
<summary>Example JSON</summary>

```json
{
  "success": true,
  "matches": [
    {
      "name": "Lake",
      "text": "middle",
      "lake": true,
      "children": []
    }
  ]
}
```

</details>

## Parse Tree Export

The parse tree export is separate from JSON output.

JSON output represents exported AST nodes.

DOT export is mainly useful for debugging the parse tree structure.

To export a parse tree:

```bash
php bin/phpeg parse \
  --grammar=grammar.cleanpeg \
  --input=input.txt \
  --tree-format=dot \
  --tree-output=debug/tree.dot
```

Currently, `dot` is the only supported tree format.

If any other value is passed to `--tree-format`, the command fails with a CLI error.

### JSON output and tree output together

`--output` and `--tree-output` are independent.

Use `--output` for the JSON AST export:

```bash
--output=result.json
```

Use `--tree-output` for the DOT parse tree export:

```bash
--tree-output=debug/tree.dot
```

Example:

```bash
php bin/phpeg parse \
  --grammar=grammar.cleanpeg \
  --input=input.txt \
  --json-style=full \
  --output=result.json \
  --tree-format=dot \
  --tree-output=debug/tree.dot
```

This writes:

- JSON AST output to `result.json`
- parse tree DOT output to `debug/tree.dot`

## Errors

The CLI only writes JSON on successful parses.

CLI errors and parse errors are rendered as clean console errors, not JSON.

The command returns a failure response when:

- `--grammar` is missing
- `--input` is missing
- the grammar file cannot be loaded
- the input file cannot be read
- the grammar format is invalid
- the parse fails
- an unsupported tree format is requested
- `--tree-format` is provided without a valid `--tree-output`

Missing required flags and invalid grammar settings are shown with a short `ERROR` banner and a hint when possible.

## Notes

- `--start-rule` is available for grammars that support it.
- `simple` JSON is best for shell usage, `jq`, and quick inspection.
- `full` JSON is best for debugging, tooling, and integrations that need offsets or metadata.
- Parse tree export is intended for debugging parser structure, not for source-preserving AST editing.
- The CLI does not infer JSON format from the output file extension.

## Related Docs

- [AST](ast.md)
