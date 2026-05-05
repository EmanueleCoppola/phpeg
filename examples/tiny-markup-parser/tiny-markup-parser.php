<?php

declare(strict_types=1);

use EmanueleCoppola\PHPeg\Loader\CleanPeg\CleanPegGrammarLoader;
use EmanueleCoppola\PHPeg\Support\AstPrinter;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$baseDir = __DIR__;
$grammarFile = $baseDir . '/tiny-markup.cleanpeg';
$inputFile = $baseDir . '/tiny-markup.tm';

$grammar = (new CleanPegGrammarLoader(skipPattern: null))->fromFile($grammarFile, startRule: 'Document');

$source = file_get_contents($inputFile);
if ($source === false) {
    throw new RuntimeException(sprintf('Unable to read tiny-markup input file: %s', $inputFile));
}

$source = rtrim($source, "\r\n");

$result = $grammar->parseDocument($source);

echo 'Parse success: yes' . PHP_EOL;
echo 'Root: ' . $result->root()->name() . PHP_EOL;
echo 'Elements: ' . $result->query('Element')->count() . PHP_EOL;
echo 'Text nodes: ' . $result->query('Text')->count() . PHP_EOL;
echo PHP_EOL;
echo 'AST:' . PHP_EOL;
echo AstPrinter::print($result->root()) . PHP_EOL;
