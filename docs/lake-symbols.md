# Lake Symbols

This repository's lake nodes are inspired by the paper "Lake Symbols for Island Parsing" by Okuda, K. and Chiba, S.

Source paper:

- [Lake Symbols for Island Parsing](papers/lake-symbols-for-island-parsing.pdf)
- arXiv: https://arxiv.org/abs/2010.16306

## The Idea

Lake symbols are a way to parse input that is mostly "water" with a few structured "islands" inside it.

The basic workflow is:

- describe the parts you care about
- let the parser handle the surrounding text
- keep the grammar focused on the interesting structure instead of repeating catch-all rules everywhere

Without lake symbols, you usually end up writing broad fallback rules by hand. Those rules are often fragile, hard to reuse, and easy to get wrong when the surrounding text becomes more varied.

## Why It Matters

Lake symbols are useful when:

- the input is mostly unstructured text
- only a few embedded constructs matter
- the host language is incomplete or unknown
- you want source-preserving editing over partially structured documents

In other words, they help when the document is mostly not the thing you want to parse. HTML with embedded data, config files with comments, code mixed with text, and similar inputs are good fits.

## How PHPeg Uses The Idea

PHPeg implements lake nodes as an opt-in grammar expression integrated with:

- the immutable grammar model
- the parser runtime
- AST generation
- source-preserving printing

The implementation adapts the concept for PHP and keeps it consistent with the rest of the library.

For AST behavior and source-preserving editing details, see [AST](ast.md).

## Lake Symbol And Water

In this repository, the lake symbol is the wildcard that stands for "whatever water can be consumed here until the grammar reaches a safe continuation".

`water` is the surrounding text that is not part of the island you want to model.

That distinction matters:

- the lake symbol tells the parser where it may skip over irrelevant input
- water is the material being skipped
- islands are the structured parts you still want to recognize explicitly

So the lake symbol is not a replacement for the grammar. It is the mechanism that lets the grammar keep its focus while the parser handles the background text safely.

## Water Annotations

Rules can be marked with `@water` in PEG and CleanPeg grammars.
Named lake profiles can be declared with the angled lake syntax, and they apply only to a lake with the same name.

When a lake is matching input:

- if the lake has a matching named lake profile, that profile is used first and only that profile is treated as local water
- otherwise the parser uses the grammar-wide `@water` rules
- if nothing matches, the parser falls back to generic single-character water

That means you can teach the parser about common kinds of water once, for example:

- whitespace
- comments
- strings
- punctuation runs

This has two benefits:

1. the grammar becomes clearer because the reusable water is named once
2. lake matching becomes more efficient because longer spans of water can be consumed in one step instead of character by character

In practice:

- `@water` is a shared fallback for unnamed lakes and for named lakes without a local profile
- a named lake declaration gives that lake its own reusable background pattern without changing the island grammar itself

## Practical Example

Suppose you want to parse a document that contains blocks inside a lot of surrounding noise:

```cleanpeg
Program = (Block / ~)* EOF
Block = "{" (Block / ~)* "}"
Start = Program
```

Here:

- `Block` is the island
- `~` is the lake symbol
- everything else is water

Now compare that with a version that knows about structured water:

```cleanpeg
Program = (Block / ~)* EOF
Block = "{" (Block / ~)* "}"
@water
Comment = r'//[^\n]*(?:\n|$)'
@water
String = r'"(?:\\.|[^"])*"'
@water
Whitespace = r'[ \t\r\n]+'
Start = Program
```

The second grammar is more useful when the surrounding text is not just random characters. It lets the parser skip comments, strings, and whitespace as reusable chunks instead of treating all of them as undifferentiated single characters.

If you need a lake with its own local background shape, declare a named lake profile:

```cleanpeg
<BodyWater> = r'[^{}]+'
Program = "{" <BodyWater> "}"
@water
Whitespace = r'[ \t\r\n]+'
```

In that example:

- `BodyWater` is a named lake profile
- `<BodyWater>` uses that local profile first
- `Whitespace` remains available as grammar-wide water for other lakes that do not have a local profile

The result is a grammar that is:

- easier to read
- easier to extend
- less repetitive
- better suited to source-preserving workflows, because the skipped text is still handled deliberately rather than discarded as an afterthought

## Practical Summary

In this repository, lake nodes are a small abstraction for saying:

- parse the interesting island here
- let the parser handle the surrounding water
- keep the skipped text intact for query, mutation, and printing
