# Examples

This repository includes end-to-end examples that mirror the main library features.

## Example Catalog

| Example | What It Demonstrates | Entry Point |
|---|---|---|
| Calculator with CleanPeg | Grammar loading, parsing, and expression evaluation | [`examples/calculator-cleanpeg/calculator-cleanpeg.php`](../examples/calculator-cleanpeg/calculator-cleanpeg.php) |
| JSON parser | CleanPeg grammar structure and tree generation | [`examples/json-parser/json-parser.php`](../examples/json-parser/json-parser.php) |
| Nginx config editing | Source-preserving AST editing | [`examples/nginx-config-edit/nginx-config-edit.php`](../examples/nginx-config-edit/nginx-config-edit.php) |
| Dotenv config editing | Source-preserving edits over environment files | [`examples/dotenv-config-edit/dotenv-config-edit.php`](../examples/dotenv-config-edit/dotenv-config-edit.php) |
| Tiny markup parsing | Named captures in CleanPeg | [`examples/tiny-markup-parser/tiny-markup-parser.php`](../examples/tiny-markup-parser/tiny-markup-parser.php) |
| Recursive language editing | Recursive grammars and AST mutation | [`examples/recursive-language/recursive_language_builder.php`](../examples/recursive-language/recursive_language_builder.php) |
| Recursive language from PEG | The same grammar loaded from classic PEG syntax | [`examples/recursive-language/recursive_language_from_peg.php`](../examples/recursive-language/recursive_language_from_peg.php) |
| Bixby-style parsing | A larger PEG-style grammar in CleanPeg | [`examples/bixby-language-parser/bixby-language-parser.php`](../examples/bixby-language-parser/bixby-language-parser.php) |
| Access policy parsing | CleanPeg parsing of a text policy file | [`examples/access-policy-parser/access-policy-parser.php`](../examples/access-policy-parser/access-policy-parser.php) |

## What To Read First

If you want a practical tour of the library, start with:

1. the root [README](../README.md)
2. the [grammar reference](grammar/README.md)
3. the [AST reference](ast/README.md)
4. the [CLI reference](cli.md)

## Notes

- Examples are designed to be run from the repository root unless a specific script says otherwise.
- When an example edits source, the input and output files in that folder show the full story.
