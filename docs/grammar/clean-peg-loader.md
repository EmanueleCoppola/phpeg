# CleanPeg Loader

CleanPeg is a compact PEG-like syntax that compiles into the same runtime model as the builder and classic PEG loader.

It is designed for concise grammar files that still read clearly in a PHP project.

## When To Use It

Use CleanPeg when:

- you want grammar definitions in a compact text format
- you want a loader that is still easy to read in reviews
- you want a lightweight syntax without giving up the PHPeg AST runtime

## Loader API

```php
use EmanueleCoppola\PHPeg\Loader\CleanPeg\CleanPegGrammarLoader;

$loader = new CleanPegGrammarLoader();
$grammar = $loader->fromString($source, startRule: 'Start');
```

### Constructor

`new CleanPegGrammarLoader(?string $skipPattern = '[ \t\r\n]*')`

- `skipPattern` controls the optional whitespace skipper inserted before skippable atoms.
- Pass `null` to disable automatic skipping.

### Loading Methods

- `fromString(string $source, ?string $startRule = null): Grammar`
- `fromFile(string $path, ?string $startRule = null): Grammar`

## Core Syntax

### Rule Declaration

Each rule uses `=`:

```cleanpeg
name = expression
```

Rules are separated by newlines.

### Literals

Double-quoted strings are exact matches.

```cleanpeg
"if"
"("
")"
```

Common escapes such as `\"`, `\\`, `\n`, and `\t` are supported.

### Regex Literals

Regex terminals use raw notation:

```cleanpeg
r'[0-9]+'
r'\d*\.\d*|\d+'
```

The pattern is compiled into a builder `regex()` expression and matched at the current offset.
Add a trailing `i` to make the terminal case-insensitive:

```cleanpeg
"if"i
r'[A-Z]+'i
```

### Case Sensitivity

Use `@insensitive` or `@sensitive` before a rule to set the default case mode for that rule and its descendants.
The nearest override wins, so a sensitive child can sit inside an insensitive parent, and a terminal suffix `i` can override a sensitive scope for a single literal or regex.

```cleanpeg
@insensitive
Start = Prefix
Prefix = "abc"

@sensitive
Strict = "Ab"
Loose = "ab"i
```

### Sequences

Whitespace between expressions means sequence.

```cleanpeg
expression term
```

### Ordered Choice

Ordered choice uses `/`.

```cleanpeg
string / number / identifier
```

### Grouping

Parentheses control precedence.

```cleanpeg
(number / "(" expression ")")*
```

### Quantifiers

Supported postfix quantifiers:

- `?` means zero or one occurrence. The expression is optional.
- `*` means zero or more occurrences. The expression may be absent or repeat.
- `+` means one or more occurrences. The expression must appear at least once.

Examples:

```cleanpeg
sign = ("+" / "-")?
digits = r'[0-9]+'
list = item*
```

### Built-In `EOF`

`EOF` is built in and compiles to the end-of-input expression.

```cleanpeg
start = expression EOF
```

### Named Captures

Use `name@Expression` to capture the text matched by `Expression` under a reusable name.
If the same capture name appears again within the same rule match, PHPeg requires the later match to produce the same text.

```cleanpeg
OpenTagName = r'[A-Za-z][A-Za-z0-9-]*'
CloseTagName = r'[A-Za-z][A-Za-z0-9-]*'
Element = "<" tag@OpenTagName ">" Content* "</" tag@CloseTagName ">"
```

That grammar accepts `<note>text</note>` and rejects `<note>text</div>` because both `tag@...` captures must resolve to the same value.

Named captures are useful for paired delimiters, matching identifiers, and compact structural checks that should stay in the grammar instead of being handled after parsing.

### Span And Length Checks

CleanPeg does not currently expose inline span comparators.
If you need a check based on two alternatives ending at the same or different offset, use the fluent builder methods `sameSpan()` and `differentSpan()`.

## Lake Nodes

Lake nodes are the island-parsing primitive in PHPeg.

Use `~` or `<>` to mark an unnamed lake:

```cleanpeg
body = "{" ~ "}"
alt_body = "{" <> "}"
```

Use `<Name>` to give the lake node a name:

```cleanpeg
named = "{" <BodyWater> "}"
```

`~` and `<>` are equivalent for unnamed lakes.

Lake nodes consume water until the next valid continuation in the grammar. That lets you describe only the interesting islands and leave the surrounding text as generic water.

Practical effects:

- the unnamed lake node is named `Lake`
- a named lake node uses the provided name, such as `BodyWater`
- unchanged documents still print back byte-for-byte identical
- lake nodes can be queried like regular AST nodes

Example:

```cleanpeg
Program = (Function / ~) * EOF
Function = "function" Spacing Identifier Spacing "(" <> ")" Spacing Block
Block = "{" ~ "}"
```

In that grammar, the top-level lake stops before `function` or EOF, the parameter lake stops before `)`, and the block lake stops before `}`.

Lake nodes are especially useful for island parsing, partial grammars, and source-preserving editing of documents that contain a lot of irrelevant text.

### Water Symbols

Use `@water` before a rule to mark it as reusable background content for lake parsing.
Use `<Name> = ...` to declare a named lake profile that applies only to `<Name>` with the same name.

```cleanpeg
@water
Whitespace = r'[ \t\r\n]+'
```

Water rules let you name common fragments like whitespace, comments, or strings once and reuse them as lake-friendly background content.

Named lake profiles let you keep that reusable background local to a specific lake:

```cleanpeg
<BodyWater> = r'[^{}]+'
Program = "{" <BodyWater> "}"
@water
Whitespace = r'[ \t\r\n]+'
```

In that example, `<BodyWater>` prefers the local `BodyWater` profile, while other lakes still fall back to the grammar-wide `@water` rules.

If you want to read more about lake and water symbols, see [docs/lake-symbols.md](../lake-symbols.md).

### Comments

Line comments start with `#`.

```cleanpeg
# this is ignored
number = r'[0-9]+'
```

## Whitespace Skipping

CleanPeg can insert a skip expression before literals, regex terminals, rule references, and `EOF`.

Default skip pattern:

```txt
[ \t\r\n]*
```

With the default setting, these grammars:

```cleanpeg
expression = term (("+" / "-") term)*
```

can parse both:

- `1+2`
- `1 + 2`

Disable skipping with:

```php
$loader = new CleanPegGrammarLoader(skipPattern: null);
```

## Example Grammar

```cleanpeg
Number = r'[0-9]+'
Factor = Number / "(" Expression ")"
Term = Factor (("*" / "/") Factor)*
Expression = Term (("+" / "-") Term)*
Start = Expression EOF
```

## Runtime Output

CleanPeg compiles to the same runtime model as the builder.

- `Grammar::rules()` returns the named rule map.
- `Grammar::startRule()` returns the configured start rule name.
- `Grammar::parse()` returns a `ParseResult`.
- `Grammar::parseDocument()` returns a `ParsedDocument`.

## Practical Notes

- Use CleanPeg for compact grammars that still need the full PHPeg AST runtime.
- Use `EOF` explicitly when you want full-input matching.
- Disable whitespace skipping when token boundaries matter.
- Keep inserted nodes explicit when you plan to print a modified document.
 - For AST querying, mutation, and source-preserving printing details, see [`docs/ast.md`](../ast.md).
