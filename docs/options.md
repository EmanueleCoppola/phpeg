# Parser Options

`ParserOptions` controls the trade-off between speed, memory, diagnostics, and AST text handling.

Use `ParserOptions::defaults()` when you want the library's general-purpose behavior.

## Options Grid

| Option | Default | What It Changes | Use It When | Tradeoff |
|---|---:|---|---|---|
| `memoizationEnabled` | `true` | Caches rule matches during parsing. | You want the normal packrat-style speedup on repeated subexpressions. | Uses more memory, especially on large inputs. |
| `maxCacheEntries` | `null` | Caps how many memoized entries the parser keeps. | You need to bound cache growth on very large or long-lived parses. | A low limit can evict useful entries and reduce the benefit of memoization. |
| `optimizeErrors` | `false` | Reduces failure bookkeeping when the parse succeeds. | Successful parses matter more than detailed failure reporting. | Error messages can become less detailed or less precise. |
| `reuseEmptyMatches` | `false` | Reuses zero-width matches by offset. | Your grammar naturally produces many empty matches and you want to avoid recomputing them. | Can make edge cases harder to reason about if the grammar relies on repeated empty matches. |
| `lazyNodeText` | `true` | Delays loading original AST node text until it is needed. | You want source-preserving ASTs without eagerly materializing all node text. | Turning it off can simplify debugging, but it increases upfront text copying. |

## Practical Profiles

These are not named presets in the API. They are the combinations that usually make sense in practice.

```php
use EmanueleCoppola\PHPeg\Parser\ParserOptions;
```

### Balanced Default

```php
$options = ParserOptions::defaults();
```

Use this when you want the safest general-purpose behavior.

- memoization stays on
- error tracking stays accurate
- AST node text stays lazy

### Speed-Oriented

```php
$options = ParserOptions::defaults()
    ->withOptimizeErrors(true)
    ->withReuseEmptyMatches(true);
```

Use this when successful parses dominate and you want to reduce bookkeeping.

- lower success-path overhead
- better fit for batch parsing
- still keeps memoization enabled

### Memory-Oriented

```php
$options = ParserOptions::defaults()
    ->withMemoization(false)
    ->withMaxCacheEntries(1_000);
```

Use this when cache growth matters more than repeated-rule speedups.

- lower peak memory use
- useful for mostly linear grammars
- can be slower if the grammar revisits the same rules often

### Debugging-Oriented

```php
$options = ParserOptions::defaults()
    ->withLazyNodeText(false);
```

Use this when you want node text to be materialized immediately and you are inspecting parse results.

- easier to inspect node text during debugging
- slightly more eager allocation
- useful in tests and diagnostics

## Related Pages

- [Quick start](../README.md#quick-start)
- [AST editing](ast.md)
