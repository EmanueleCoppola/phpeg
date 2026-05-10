<?php

declare(strict_types=1);

use EmanueleCoppola\PHPeg\Loader\Peg\PegGrammarLoader;
use EmanueleCoppola\PHPeg\Support\AstPrinter;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$input = <<<'TEXT'
block main {
    print "hello"

    block nested {
        print "inside"

        block deeper {
            print "deep"
        }
    }

    print "done"
}
TEXT;

$grammar = (new PegGrammarLoader())->fromFile(__DIR__ . '/recursive_language.peg');
$result = $grammar->parse($input, 'Program');

echo 'Parse success: ' . ($result->isSuccess() ? 'yes' : 'no') . PHP_EOL;

if (!$result->isSuccess()) {
    echo $result->error()?->message() . PHP_EOL;
    exit(1);
}

$root = $result->node();

echo 'Root: ' . $root?->name() . PHP_EOL;
echo 'Final offset: ' . $result->finalOffset() . PHP_EOL;
echo PHP_EOL . 'AST:' . PHP_EOL;
echo AstPrinter::print($root) . PHP_EOL;
