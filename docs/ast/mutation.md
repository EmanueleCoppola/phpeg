# Source-Preserving Mutation

PHPeg supports source-preserving tree mutation on parsed documents.

The usual workflow is:

1. parse source with `parseDocument()`
2. query the nodes you want to change
3. replace, insert, or remove AST nodes
4. render the edited tree back to text with `print()`
5. validate the result by reparsing the printed source with the same grammar

## Supported Node Operations

- `prependNode()`
- `appendNode()`
- `before()`
- `after()`
- `replaceWith()`
- `remove()`

These operations preserve the surrounding original source whenever possible.

## Supported Collection Operations

- `appendNode()`
- `prependNode()`
- `each()`

Collection insertion methods clone the provided node for each target.

## Factory Nodes

Use `AstNodeFactory` for inserted or replacement nodes.

```php
use EmanueleCoppola\PHPeg\Ast\AstNodeFactory;

$factory = new AstNodeFactory();
$factory->node('Statement', text: '    print "inserted"' . "\n");
$factory->token('Identifier', 'renamed');
```

Inserted nodes should carry explicit render text when the printer needs a concrete string to emit.

## Replacement Example

```php
use EmanueleCoppola\PHPeg\Ast\AstNodeFactory;
use EmanueleCoppola\PHPeg\Loader\CleanPeg\CleanPegGrammarLoader;

$grammar = (new CleanPegGrammarLoader())->fromFile('nginx-config-grammar.cleanpeg', startRule: 'NginxConfig');
$document = $grammar->parseDocument(file_get_contents('nginx.conf'));
$factory = new AstNodeFactory();

$document->query('Directive[name="worker_processes"] Number[text="2"]')->first()?->replaceWith(
    $factory->token('Number', '4')
);

echo $document->print();
```

## Validation

`validatePrintedSource()` reparses the rendered source with the original grammar.
`validate()` is an alias.

Use validation when you want to confirm that the edited document still parses cleanly after source-preserving mutations.
