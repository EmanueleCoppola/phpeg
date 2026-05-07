# Recursive Grammars

## How Recursive Rules Work In PHPeg

PHPeg resolves rule references lazily at parse time through `RuleReferenceExpression`.

That means a rule may safely refer to:

- itself
- another rule that later refers back to it
- a rule defined later in the grammar

This supports common nested-language structures such as blocks, parentheses, lists, and trees.

## Supported Recursion

PHPeg supports right recursion, nested recursion, and left recursion with automatic bottom-up parsing.

Example:

```peg
Program   <- Spacing Block Spacing
Block     <- 'block' Spacing Identifier Spacing '{' Spacing Statement* Spacing '}'
Statement <- PrintStatement / Block
```

`Block` is recursive because it contains `Statement*`, and `Statement` may expand back into `Block`.

This is supported because the recursive call happens after the parser has already consumed part of the input.

## Left Recursion

PEG parsers based on recursive descent cannot evaluate direct left recursion safely without special handling.
PHPeg detects left-recursive grammars automatically and switches to the bottom-up path for them.

When that happens, the parser rescans the rule until the match stops growing.

This grammar triggers left-recursive parsing:

```peg
Expr <- Expr '+' Term / Term
```

The first alternative asks `Expr` to parse `Expr` again at the exact same input position. PHPeg detects that shape and switches to the bottom-up path automatically.

If a grammar is not left-recursive, PHPeg stays on the normal packrat path.

## Rewriting Left Recursion

Do not write:

```peg
Expr <- Expr '+' Term / Term
Term <- Number
```

Rewrite it as:

```peg
Expr   <- Term ('+' Term)*
Term   <- Number
Number <- [0-9]+
```

This preserves the intended structure while making the grammar PEG-friendly.

If you prefer, you can keep the left-recursive form and let PHPeg select the bottom-up path automatically.

## Mutual Recursion

Mutual recursion is allowed when each recursive step consumes input before the next recursive rule call.

Example:

```peg
A <- 'a' B / 'x'
B <- 'b' A / 'y'
```

Valid inputs include:

- `x`
- `ay`
- `abx`
- `ababx`

## Practical Guidance

- Prefer recursive rules for nested constructs such as blocks, arrays, objects, and grouped expressions.
- Avoid left-recursive expression grammars.
- When designing operator grammars, rewrite them into iterative suffix forms such as `Term ('+' Term)*`.
- If a recursive grammar fails, inspect the error offset and nearby snippet first. PHPeg keeps those diagnostics available even inside nested recursive parses.
