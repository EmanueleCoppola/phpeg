# PHPeg

[![Tests](https://img.shields.io/github/actions/workflow/status/EmanueleCoppola/phpeg/tests.yml?branch=main&label=tests)](https://github.com/EmanueleCoppola/phpeg/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/emanuelecoppola/phpeg.svg)](https://packagist.org/packages/emanuelecoppola/phpeg)
[![Total Downloads](https://img.shields.io/packagist/dt/emanuelecoppola/phpeg.svg)](https://packagist.org/packages/emanuelecoppola/phpeg)
[![License](https://img.shields.io/packagist/l/emanuelecoppola/phpeg.svg)](LICENSE)

PHPeg is a modern PEG parsing library for PHP.

It gives you:

- a fluent PHP grammar builder
- two grammar loaders: CleanPeg for compact grammars with built-in conveniences, Classic PEG for explicit traditional PEG syntax
- AST querying, mutation, and source-preserving printing
- configurable parsing trade-offs for speed, memory, and diagnostics
- left-recursive grammars are detected and handled automatically

If you want to parse PHP-native grammars and still get serious AST tooling and source-preserving editing, this library is built for that.

## Installation

```bash
composer require emanuelecoppola/phpeg
```

## Quick Start

This example parses a tiny `env`-style line and then replaces its value in place.

```php
<?php

declare(strict_types=1);

use EmanueleCoppola\PHPeg\Ast\AstNodeFactory;
use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;

require __DIR__ . '/vendor/autoload.php';

$g = GrammarBuilder::create();

$grammar = $g->grammar('Start')
    ->rule('Word', $g->oneOrMore($g->charClass('[A-Z_]')))
    ->rule('Value', $g->oneOrMore($g->charClass('[a-z]')))
    ->rule('Assignment', $g->seq(
        $g->ref('Word'),
        $g->literal('='),
        $g->ref('Value'),
    ))
    ->rule('Start', $g->seq($g->ref('Assignment'), $g->eof()))
    ->build();

$input = 'APP_ENV=local';
$result = $grammar->parse($input);

if (!$result->isSuccess()) {
    echo $result->error()?->message() . PHP_EOL;
    exit(1);
}

echo $result->node()?->name() . PHP_EOL;
echo $result->matchedText() . PHP_EOL;

$document = $grammar->parseDocument($input);
$factory = new AstNodeFactory();

$document->query('Value')->first()?->replaceWith(
    $factory->token('Value', 'production')
);

echo $document->print();
```

The output is:

```text
Start
APP_ENV=local
APP_ENV=production
```

### Parser options

Parser behavior is configured through [`ParserOptions`](src/Parser/ParserOptions.php).
The available options and recommended combinations are documented in [`docs/options.md`](docs/options.md).

## Documentation

- Documentation index: [`docs/README.md`](docs/README.md)
- Grammar reference: [`docs/grammar/README.md`](docs/grammar/README.md)
- Fluent PHP builder: [`docs/grammar/fluent-php-builder.md`](docs/grammar/fluent-php-builder.md)
- CleanPeg loader: [`docs/grammar/clean-peg-loader.md`](docs/grammar/clean-peg-loader.md)
- Classic PEG loader: [`docs/grammar/classic-peg-loader.md`](docs/grammar/classic-peg-loader.md)
- Lake symbols: [`docs/lake-symbols.md`](docs/lake-symbols.md)
- AST overview: [`docs/ast.md`](docs/ast.md)
- Parser options: [`docs/options.md`](docs/options.md)
- CLI: [`docs/cli.md`](docs/cli.md)
- Examples catalog: [`docs/examples.md`](docs/examples.md)
- Troubleshooting: [`docs/troubleshooting.md`](docs/troubleshooting.md)
- Benchmarks: [`benchmarks/README.md`](benchmarks/README.md)

## Examples

The repository includes end-to-end examples for:

- calculator parsing with CleanPeg
- JSON parsing
- nginx config editing
- dotenv config editing
- tiny-markup parsing with named captures in CleanPeg
- recursive language AST editing
- Bixby-style language parsing
- access-policy parsing

Useful entry points:

- [`docs/examples.md`](docs/examples.md)
- [`examples/calculator-cleanpeg/calculator-cleanpeg.php`](examples/calculator-cleanpeg/calculator-cleanpeg.php)
- [`examples/json-parser/json-parser.php`](examples/json-parser/json-parser.php)
- [`examples/nginx-config-edit/nginx-config-edit.php`](examples/nginx-config-edit/nginx-config-edit.php)
- [`examples/dotenv-config-edit/dotenv-config-edit.php`](examples/dotenv-config-edit/dotenv-config-edit.php)
- [`examples/tiny-markup-parser/tiny-markup-parser.php`](examples/tiny-markup-parser/tiny-markup-parser.php)
- [`examples/recursive-language/recursive_language_builder.php`](examples/recursive-language/recursive_language_builder.php)
- [`examples/access-policy-parser/access-policy-parser.php`](examples/access-policy-parser/access-policy-parser.php)

## References

This repository implements lake symbols for island parsing, and its grammar tooling is also inspired by Arpeggio.

Okuda, K., Chiba, S.  
"Lake Symbols for Island Parsing"  
https://arxiv.org/abs/2010.16306

The paper is included in this repository as [docs/papers/lake-symbols-for-island-parsing.pdf](docs/papers/lake-symbols-for-island-parsing.pdf) for reference.

Dejanović I., Milosavljević G., Vaderna R.  
"Arpeggio: A flexible PEG parser for Python"  
https://doi.org/10.1016/j.knosys.2015.12.004
