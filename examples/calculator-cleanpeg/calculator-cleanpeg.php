<?php

declare(strict_types=1);

use EmanueleCoppola\PHPeg\Ast\AstNode;
use EmanueleCoppola\PHPeg\Loader\CleanPeg\CleanPegGrammarLoader;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$grammar = (new CleanPegGrammarLoader())->fromFile(
    __DIR__ . '/calculator-cleanpeg.cleanpeg',
    startRule: 'Start',
);

$samples = [
    '1+2+3',
    '10 + 20 + 30',
    '7 + 0.5 + 1.25',
    '1 +',
];

foreach ($samples as $sample) {
    $result = $grammar->parse($sample);

    echo 'Input: ' . $sample . PHP_EOL;

    if (!$result->isSuccess()) {
        echo 'Parse success: no' . PHP_EOL;
        echo 'Error: ' . $result->error()?->message() . PHP_EOL;
        echo str_repeat('-', 40) . PHP_EOL;
        continue;
    }

    $document = $grammar->parseDocument($sample);
    $sum = sumNumbers($document->query('Number')->all());
    echo 'Parse success: yes' . PHP_EOL;
    echo 'Result: ' . formatNumber($sum) . PHP_EOL;
    echo str_repeat('-', 40) . PHP_EOL;
}

/**
 * @param list<AstNode> $numbers
 */
function sumNumbers(array $numbers): float
{
    $total = 0.0;

    foreach ($numbers as $number) {
        $total += (float) $number->text();
    }

    return $total;
}

/**
 * Formats a numeric result without trailing decimal noise.
 */
function formatNumber(float $value): string
{
    if (abs($value - round($value)) < 0.0000001) {
        return (string) (int) round($value);
    }

    $formatted = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');

    return $formatted === '' ? '0' : $formatted;
}
