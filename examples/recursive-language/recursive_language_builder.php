<?php

declare(strict_types=1);

use EmanueleCoppola\PHPeg\Builder\GrammarBuilder;
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

$g = GrammarBuilder::create();

$grammar = $g->grammar('Program')
    ->rule('Program', $g->seq(
        $g->ref('Spacing'),
        $g->ref('Block'),
        $g->ref('Spacing'),
    ))
    ->rule('Block', $g->seq(
        $g->literal('block'),
        $g->ref('Spacing'),
        $g->ref('Identifier'),
        $g->ref('Spacing'),
        $g->literal('{'),
        $g->ref('Spacing'),
        $g->zeroOrMore($g->ref('Statement')),
        $g->ref('Spacing'),
        $g->literal('}'),
    ))
    ->rule('Statement', $g->choice(
        $g->seq(
            $g->ref('Spacing'),
            $g->choice(
                $g->ref('PrintStatement'),
                $g->ref('Block'),
            ),
        ),
    ))
    ->rule('PrintStatement', $g->seq(
        $g->literal('print'),
        $g->ref('Spacing'),
        $g->ref('String'),
        $g->ref('Spacing'),
    ))
    ->rule('Identifier', $g->seq(
        $g->charClass('[a-zA-Z_]'),
        $g->zeroOrMore($g->charClass('[a-zA-Z0-9_]')),
    ))
    ->rule('String', $g->seq(
        $g->literal('"'),
        $g->zeroOrMore($g->ref('StringChar')),
        $g->literal('"'),
    ))
    ->rule('StringChar', $g->seq(
        $g->not($g->literal('"')),
        $g->any(),
    ))
    ->rule('Spacing', $g->zeroOrMore($g->charClass('[ \t\r\n]')))
    ->build();

$result = $grammar->parse($input);

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
