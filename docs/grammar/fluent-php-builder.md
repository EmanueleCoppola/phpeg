# Fluent PHP Builder

The fluent PHP builder is the most direct way to define a grammar in PHPeg.
It keeps the grammar in native PHP code and compiles it into the same immutable `Grammar` model used by the loaders.

## When To Use It

Use the builder when:

- you want the grammar to live alongside application code
- you prefer explicit PHP over a grammar file format
- you want to compose expressions programmatically

## Basic Shape

```php
use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;

$g = GrammarBuilder::create();

$grammar = $g->grammar('Start')
    ->rule('Expression', ...)
    ->rule('Start', ...)
    ->build();
```

The builder has four steps:

1. `GrammarBuilder::create()` creates a new builder instance.
2. `grammar($startRule)` optionally declares the start rule name.
3. `rule($name, $expression, ?bool $isWater = false)` adds or replaces a rule.
4. `lakeRule($name, $expression)` adds or replaces a named lake profile.
5. `build()` returns the immutable `Grammar`.

If you do not call `grammar()`, the first rule you add becomes the start rule.

## Builder Methods

### Grammar Definition

- `grammar(string $startRule): self`
- `rule(string $name, ExpressionInterface $expression, bool $isWater = false, ?bool $ignoreCase = null): self`
- `lakeRule(string $name, ExpressionInterface $expression): self`
- `build(): Grammar`

### Expression Constructors

- `literal(string $literal, ?bool $ignoreCase = null): ExpressionInterface`
- `regex(string $pattern, ?bool $ignoreCase = null): ExpressionInterface`
- `charClass(string $pattern, ?bool $ignoreCase = null): ExpressionInterface`
- `seq(ExpressionInterface ...$expressions): ExpressionInterface`
- `choice(ExpressionInterface ...$expressions): ExpressionInterface`
- `zeroOrMore(ExpressionInterface $expression): ExpressionInterface`
- `oneOrMore(ExpressionInterface $expression): ExpressionInterface`
- `optional(ExpressionInterface $expression): ExpressionInterface`
- `ref(string $name): ExpressionInterface`
- `capture(string $name, ExpressionInterface $expression, ?bool $ignoreCase = null): ExpressionInterface`
- `any(): ExpressionInterface`
- `eof(): ExpressionInterface`
- `and(ExpressionInterface $expression): ExpressionInterface`
- `not(ExpressionInterface $expression): ExpressionInterface`
- `lake(?string $name = null, bool $capture = true): ExpressionInterface`
- `sameSpan(ExpressionInterface $left, ExpressionInterface $right): ExpressionInterface`
- `differentSpan(ExpressionInterface $left, ExpressionInterface $right): ExpressionInterface`

### Aliases

- `one()` is an alias for `oneOrMore()`
- `many()` is an alias for `zeroOrMore()`
- `maybe()` is an alias for `optional()`
- `or()` is an alias for `choice()`

## Expression Semantics

### `literal()`

Matches an exact string at the current offset.

```php
$g->literal('if');
$g->literal('if', true);
```

Pass `true` to make the terminal case-insensitive for that node only.

### `regex()`

Matches an anchored regular expression at the current offset.
Use this for token-like fragments where a direct regex is clearer than a character class loop.

```php
$g->regex('[0-9]+');
$g->regex('[A-Z]+', true);
```

Pass `true` to force a case-insensitive regex terminal.

### `charClass()`

Matches a single character from a bracket expression.

```php
$g->charClass('[a-zA-Z_]');
$g->charClass('[a-zA-Z_]', true);
```

Pass `true` to force case-insensitive matching for that character class node.

### `seq()`

Matches each expression in order.

```php
$g->seq($g->ref('Name'), $g->literal('='), $g->ref('Value'));
```

`seq()` accepts one or more `ExpressionInterface` instances.

### `choice()`

Tries alternatives in order and returns the first successful match.

```php
$g->choice($g->ref('String'), $g->ref('Number'), $g->ref('Identifier'));
```

`choice()` accepts one or more `ExpressionInterface` instances.

### `zeroOrMore()`

Matches the wrapped expression zero or more times.

```php
$g->zeroOrMore($g->ref('Item'));
```

Use it with any single `ExpressionInterface`:

- a rule reference, such as `$g->ref('Item')`
- a literal, such as `$g->literal(',')`
- a grouped sequence, such as `$g->seq($g->literal(','), $g->ref('Item'))`
- another quantified expression, when you really want nested repetition

### `oneOrMore()`

Matches the wrapped expression one or more times.

```php
$g->oneOrMore($g->charClass('[0-9]'));
```

Use it with any single `ExpressionInterface`.

### `optional()`

Matches the wrapped expression zero or one time.

```php
$g->optional($g->literal('-'));
```

Use it with any single `ExpressionInterface`.

### `ref()`

Creates a named rule reference.

```php
$g->ref('Expression');
```

### `capture()`

Creates a named capture expression.
The first successful match stores the matched text under the capture name.
Later uses of the same capture name inside the same rule match must produce the same text.

```php
$g->seq(
    $g->literal('<'),
    $g->capture('tag', $g->ref('TagName')),
    $g->literal('>'),
    $g->ref('Content'),
    $g->literal('</'),
$g->capture('tag', $g->ref('TagName')),
$g->literal('>'),
);
```

The optional third argument follows the same case-sensitivity rules as other terminal nodes when you need the capture body to match under a local override.

That accepts `<note>text</note>` and rejects `<note>text</div>`.

When you write the same idea in CleanPeg, use `tag@TagName`.
The classic PEG loader does not currently expose this syntax.

### `any()`

Matches any single character.

```php
$g->any();
```

### `eof()`

Matches the end of input.

```php
$g->seq($g->ref('Expression'), $g->eof());
```

### `and()` and `not()`

Create positive and negative lookahead predicates.

```php
$g->and($g->literal('!'));
$g->not($g->literal(')'));
```

Predicates do not consume input. They only test whether the wrapped expression would match.

Use them with any single `ExpressionInterface`, usually a literal, reference, or short sequence.

### `sameSpan()` and `differentSpan()`

Create span comparator expressions.
Both operands are matched from the same starting offset; the comparator then checks where each operand ends.

`sameSpan()` succeeds only when both operands end at the same offset:

```php
$g->sameSpan(
    $g->choice($g->literal('ab'), $g->literal('abc')),
    $g->literal('ab'),
);
```

`differentSpan()` succeeds only when the operands end at different offsets:

```php
$g->differentSpan(
    $g->choice($g->literal('abc'), $g->literal('ab')),
    $g->literal('ab'),
);
```

Use these when the grammar needs a deterministic length or boundary check without manually inspecting matched text after parsing.
The CleanPeg and classic PEG loaders do not currently expose inline syntax for these comparator expressions.

### `lake()`

Creates a lake expression for island parsing.

```php
$g->lake();
$g->lake('BodyWater');
```

Lake nodes are the island-parsing primitive in PHPeg.

`lake()` creates an unnamed lake node by default, which is named `Lake` in the AST.
Passing a string name creates a named lake node, such as `BodyWater`.

When you write the same idea in a loader grammar, use `~` or `<>` for an unnamed lake, `<Name>` for a named lake, and `<Name> <- ...` or `<Name> = ...` for a named lake profile.

Lake nodes consume water until the compiled stop set is reached. That makes them useful when you want to describe only the interesting islands in a larger document and leave the surrounding text untouched.

Water rules are the expressions the lake can reuse as background content. In the builder, mark a rule as water by passing `true` as the third argument to `rule()`.

If you want a lake with its own local water profile, declare it with `lakeRule()` and reuse the same name in `lake()`:

```php
$grammar = $g->grammar('Program')
    ->lakeRule('BodyWater', $g->regex('[^{}]+'))
    ->rule('Program', $g->seq($g->literal('{'), $g->lake('BodyWater'), $g->literal('}')))
    ->build();
```

Practical effects:

- the unnamed lake node is named `Lake`
- a named lake node uses the provided name
- a named lake can use a local lake profile when one is declared
- unchanged documents still print back byte-for-byte identical
- lake nodes can be queried like regular AST nodes

Example:

```php
$grammar = $g->grammar('Program')
    ->rule('Program', $g->seq($g->zeroOrMore($g->choice($g->ref('Function'), $g->lake())), $g->eof()))
    ->rule('Function', $g->seq(
        $g->literal('function'),
        $g->ref('Spacing'),
        $g->ref('Identifier'),
        $g->ref('Spacing'),
        $g->literal('('),
        $g->lake(),
        $g->literal(')'),
        $g->ref('Spacing'),
        $g->ref('Block'),
    ))
    ->rule('Block', $g->seq($g->literal('{'), $g->lake(), $g->literal('}')))
    ->build();
```

In that grammar, the top-level lake stops before `function` or EOF, the parameter lake stops before `)`, and the block lake stops before `}`.

If you want to read more about lake and water symbols, see [docs/lake-symbols.md](../lake-symbols.md).

## Example Grammar

This example is shared across the three grammar styles in this repository.

```php
use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;

$g = GrammarBuilder::create();

$grammar = $g->grammar('Start')
    ->rule('Number', $g->oneOrMore($g->charClass('[0-9]')))
    ->rule('Factor', $g->choice(
        $g->ref('Number'),
        $g->seq($g->literal('('), $g->ref('Expression'), $g->literal(')')),
    ))
    ->rule('Term', $g->seq(
        $g->ref('Factor'),
        $g->zeroOrMore($g->seq(
            $g->choice($g->literal('*'), $g->literal('/')),
            $g->ref('Factor'),
        )),
    ))
    ->rule('Expression', $g->seq(
        $g->ref('Term'),
        $g->zeroOrMore($g->seq(
            $g->choice($g->literal('+'), $g->literal('-')),
            $g->ref('Term'),
        )),
    ))
    ->rule('Start', $g->seq($g->ref('Expression'), $g->eof()))
    ->build();
```

## Runtime Output

The builder compiles to the same runtime model as the loaders.

- `Grammar::rules()` returns the named rule map.
- `Grammar::startRule()` returns the configured start rule name.
- `Grammar::parse()` returns a `ParseResult`.
- `Grammar::parseDocument()` returns a `ParsedDocument`.

## Practical Notes

- Use `literal()` for exact punctuation and keywords.
- Use `charClass()` for character-by-character scanning.
- Use `regex()` when a token is easier to describe as a single anchored pattern.
- Keep recursive references explicit with `ref()`.
- Prefer `parseDocument()` when you need source-preserving editing or selector queries.
- For AST querying, mutation, and source-preserving printing details, see [`docs/ast.md`](../ast.md).
