# Classic PEG Loader

The classic PEG loader reads traditional PEG syntax and compiles it into the same PHPeg runtime model used by the builder and CleanPeg loader.

Use it when you already have PEG grammar files or you want a traditional grammar notation in a repository.

## When To Use It

Use the classic PEG loader when:

- you have existing `.peg` grammars
- you want a grammar file format that looks like classic PEG
- you want the same parser runtime, AST nodes, and source-preserving edits as the other styles

## Loader API

```php
use EmanueleCoppola\PHPeg\Loader\Peg\PegGrammarLoader;

$loader = new PegGrammarLoader();
$grammar = $loader->fromString($source);
```

### Loading Methods

- `fromString(string $source): Grammar`
- `fromFile(string $path): Grammar`

The loader has no extra configuration options.

## Core Syntax

### Rule Declaration

Each rule uses `<-`:

```peg
name <- expression
```

Rules are separated by whitespace. Newlines are optional.

### Literals

Both single and double quoted strings are supported.

```peg
'if'
"("
")"
```

Escape handling follows the tokenizer used by the loader.
Add a trailing `i` to make a literal case-insensitive:

```peg
'if'i
```

### Character Classes

Character classes are supported directly.

```peg
[0-9]
[a-zA-Z_]
```

Add a trailing `i` to make a character class case-insensitive:

```peg
[a-zA-Z_]i
```

### Sequences

Adjacent expressions form a sequence.

```peg
identifier '=' value
```

### Ordered Choice

Ordered choice uses `/`.

```peg
string / number / identifier
```

### Grouping

Parentheses control precedence.

```peg
(number / '(' expression ')')*
```

### Quantifiers

Supported postfix quantifiers:

- `?` means zero or one occurrence. The expression is optional.
- `*` means zero or more occurrences. The expression may be absent or repeat.
- `+` means one or more occurrences. The expression must appear at least once.

Examples:

```peg
sign <- ('+' / '-')?
digits <- [0-9]+
list <- item*
```

### Predicates

The loader supports both lookahead operators:

- `&` positive lookahead
- `!` negative lookahead

Examples:

```peg
keyword <- !identifier 'if'
name <- &letter letter+
```

Predicates do not consume input.

### Any Character

`.` matches any single character.

```peg
any <- .
```

### Named Captures And Span Checks

The classic PEG loader keeps the file syntax close to traditional PEG.
It does not currently support CleanPeg-style named captures such as `tag@Name`, and it does not expose inline span or length comparators.

Use one of these alternatives when you need those checks:

- CleanPeg `name@Expression` for reusable text equality inside a rule
- the fluent builder `capture()` method for the same named-capture behavior in PHP
- the fluent builder `sameSpan()` and `differentSpan()` methods for same-offset and different-offset checks

### Case Sensitivity

Use `@insensitive` or `@sensitive` before a rule to set the default case mode for that rule and its descendants.
The nearest override wins, so a sensitive child can sit inside an insensitive parent, and a terminal suffix `i` can override a sensitive scope for a single literal or character class.

```peg
@insensitive
Start <- Prefix
Prefix <- 'abc'

@sensitive
Strict <- 'Ab'
Loose <- 'ab'i
```

### Lake Nodes

Lake nodes are the island-parsing primitive in PHPeg.

Use `~` or `<>` to mark an unnamed lake:

```peg
Body <- "{" ~ "}"
AltBody <- "{" <> "}"
```

Use `<Name>` to give the lake node a name:

```peg
Named <- "{" <BodyWater> "}"
```

`~` and `<>` are equivalent for unnamed lakes.

Lake nodes consume water until the next valid continuation in the grammar. That lets you describe only the interesting islands and leave the surrounding text as generic water.

Practical effects:

- the unnamed lake node is named `Lake`
- a named lake node uses the provided name, such as `BodyWater`
- unchanged documents still print back byte-for-byte identical
- lake nodes can be queried like regular AST nodes

Example:

```peg
Program <- (Function / ~)*
Function <- "function" Spacing Identifier Spacing "(" <> ")" Spacing Block
Block <- "{" ~ "}"
```

In that grammar, the top-level lake stops before `function` or EOF, the parameter lake stops before `)`, and the block lake stops before `}`.

Lake nodes are especially useful for island parsing, partial grammars, and source-preserving editing of documents that contain a lot of irrelevant text.

### Water Symbols

Use `@water` before a rule to mark it as reusable background content for lake parsing.
Use `<Name> <- ...` to declare a named lake profile that applies only to `<Name>` with the same name.

```peg
@water
Whitespace <- [ \t\r\n]+
```

Water rules are a concise way to name common text fragments that should be consumed while a lake advances through the document.

Named lake profiles let you keep that reusable background local to a specific lake:

```peg
<BodyWater> <- [^{}]+
Program <- "{" <BodyWater> "}"
@water
Whitespace <- [ \t\r\n]+
```

In that example, `<BodyWater>` prefers the local `BodyWater` profile, while other lakes still fall back to the grammar-wide `@water` rules.

If you want to read more about lake and water symbols, see [docs/lake-symbols.md](../lake-symbols.md).

### Comments

Line comments starting with `#` or `//` are ignored.

```peg
# this is ignored
// this is also ignored
```

## End Of Input

Classic PEG does not have a built-in `EOF` keyword.

If you need end-of-input matching, define it explicitly:

```peg
EOF <- !.
start <- expression EOF
```

That keeps the syntax portable and makes the end-of-input rule visible in the grammar file.

## Example Grammar

```peg
Number <- [0-9]+
Factor <- Number / '(' Expression ')'
Term <- Factor (('*' / '/') Factor)*
Expression <- Term (('+' / '-') Term)*
EOF <- !.
Start <- Expression EOF
```

## Runtime Output

The classic PEG loader compiles into the same runtime model as the builder and CleanPeg.

- `Grammar::rules()` returns the named rule map.
- `Grammar::startRule()` returns the configured start rule name.
- `Grammar::parse()` returns a `ParseResult`.
- `Grammar::parseDocument()` returns a `ParsedDocument`.

## Practical Notes

- Use classic PEG when you already maintain grammar files in that notation.
- Define `EOF <- !.` explicitly if you need a whole-input match.
- Use `.` and predicates carefully when porting grammars from other PEG tools.
- Prefer `parseDocument()` when the grammar is used for source-preserving editing rather than simple recognition.
- For AST querying, mutation, and source-preserving printing details, see [`docs/ast.md`](../ast.md).
