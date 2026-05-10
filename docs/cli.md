# CLI

PHPeg ships with a command-line interface for loading `.peg` or `.cleanpeg` grammars, parsing input files, exporting ASTs, and inspecting parser traces.

## Commands

| Command | Purpose |
|---|---|
| `parse` | Parse an input file, export JSON, and optionally write a DOT parse tree |
| `trace` | Capture a parser trace as JSON for later inspection |
| `step` | Inspect a trace JSON file in an interactive terminal viewer |
| `benchmark` | Run the benchmark suite |
| `benchmark:compare` | Compare the latest benchmark runs |

## Parse

Parse an input file with a file-based grammar:

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt
```

By default, `parse` writes compact JSON to stdout.

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt > result.json
```

Write JSON to a file:

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt --output=result.json
```

Use the short aliases if you prefer:

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg -i input.txt -o result.json
```

Export a parse tree as Graphviz DOT at the same time:

```bash
php bin/phpeg parse \
  --grammar=grammar.cleanpeg \
  --input=input.txt \
  --tree-format=dot \
  --tree-output=debug/tree.dot
```

## Parse Options

| Option | Required | Default | Description |
|---|---:|---|---|
| `--grammar=PATH` | Yes | - | Path to the grammar file |
| `-i, --input=PATH` | Yes | - | Path to the source file to parse |
| `-o, --output=PATH` | No | stdout | Writes JSON output to a file |
| `--grammar-format=FORMAT` | No | `auto` | Grammar format: `auto`, `peg`, or `cleanpeg` |
| `--start-rule=RULE` | No | grammar default | Overrides the grammar start rule |
| `--query=SELECTOR` | No | root node | Filters exported AST nodes using the selector language |
| `--json-style=STYLE` | No | `simple` | JSON output style: `simple` or `full` |
| `--tree-format=FORMAT` | No | - | Parse tree export format. Currently only `dot` |
| `--tree-output=PATH` | Required with `--tree-format` | - | Path where the parse tree DOT file is written |

## `parse` Output

`parse` uses two JSON styles.

### `simple`

`simple` is the default style and is intended for quick inspection, shell pipelines, and `jq`.

It exports:

- `success`
- `matches`

Each node contains:

- `name`
- `text`
- `lake`
- recursive `children`

### `full`

`full` is the detailed schema for tooling and diagnostics.

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

Example:

```json
{
  "success": true,
  "grammar": {
    "path": "grammar.cleanpeg",
    "format": "cleanpeg",
    "startRule": "Start"
  },
  "input": {
    "path": "input.txt",
    "length": 13
  },
  "parse": {
    "finalOffset": 13,
    "matchedText": "APP_ENV=local"
  },
  "query": {
    "selector": "Value",
    "count": 1
  },
  "matches": [
    {
      "name": "Value",
      "text": "local",
      "originalText": "local",
      "startOffset": 8,
      "endOffset": 13,
      "isOriginal": true,
      "isModified": false,
      "isInserted": false,
      "isRemoved": false,
      "lake": false,
      "attributes": {},
      "semantic": {
        "text": "local",
        "type": "Value",
        "name": null,
        "value": null
      },
      "children": []
    }
  ]
}
```

## Querying the AST

`--query=SELECTOR` uses the same selector syntax as `ParsedDocument::query()`.

Examples:

```bash
--query='Block[name="server"]'
--query='Identifier[text="nested"]'
--query='Block > Directive:first'
```

For the full selector syntax, see [AST query language](ast/query.md).

## Grammar Format

By default, PHPeg detects the grammar format from the file extension.

```bash
php bin/phpeg parse --grammar=grammar.cleanpeg --input=input.txt
```

`--grammar-format=auto` resolves formats as follows:

| Extension | Format |
|---|---|
| `.cleanpeg` | `cleanpeg` |
| `.peg` | `peg` |

You can force the grammar format explicitly:

```bash
php bin/phpeg parse \
  --grammar=grammar.txt \
  --grammar-format=cleanpeg \
  --input=input.txt
```

When `cleanpeg` or `peg` is passed explicitly, extension inference is skipped.

## Trace

`trace` accepts the same grammar and input flags as `parse`, but writes a JSON trace document instead of AST output.

```bash
php bin/phpeg trace --grammar=grammar.cleanpeg --input=input.txt --output=trace.json
```

### Trace Options

| Option | Required | Default | Description |
|---|---:|---|---|
| `--grammar=PATH` | Yes | - | Path to the grammar file |
| `-i, --input=PATH` | Yes | - | Path to the source file to parse |
| `-o, --output=PATH` | No | stdout | Writes the trace JSON to a file |
| `--grammar-format=FORMAT` | No | `auto` | Grammar format: `auto`, `peg`, or `cleanpeg` |
| `--start-rule=RULE` | No | grammar default | Overrides the grammar start rule |

Use `trace` when you want a replayable parser trace for debugging or documentation.

## Step

`step` reads a trace JSON file and renders the grammar, input, and current step in a terminal view.

```bash
php bin/phpeg step --trace=trace.json
```

By default, the viewer starts at step `0` and shows the original grammar source.
Pass `--grammar-view=tree` to switch to the expanded node tree.

### Step Options

| Option | Required | Default | Description |
|---|---:|---|---|
| `--trace=PATH` | Yes | - | Path to a trace JSON file generated by `trace` |
| `--step=INDEX` | No | `0` | Initial step index to display |
| `--grammar-view=MODE` | No | `source` | Grammar view mode: `source` or `tree` |
| `--mode=MODE` | No | `all` | Step filter: `all`, `matched`, or `failures` |

The `--mode` flag filters which trace steps are visible in the viewer.

Interactive commands:

- `n` or `next`
- `p` or `prev`
- `g <n>` or `goto <n>`
- `a` or `all`
- `m` or `matched`
- `f` or `failures`
- `q` or `quit`
- `?` or `help`

## Errors

CLI errors are rendered as clean console errors, not JSON.

The command returns a failure response when:

- a required flag is missing
- the grammar file cannot be loaded
- the input file cannot be read
- the grammar format is invalid
- the parse fails
- an unsupported tree format is requested
- `--tree-format` is provided without `--tree-output`
- the trace file cannot be read
- the trace file is not valid JSON

## Related Docs

- [AST model](ast/model.md)
- [AST query language](ast/query.md)
- [Source-preserving printing](ast/printing.md)
- [Parser options](options.md)
