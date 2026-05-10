<?php

declare(strict_types=1);

return [
    'default' => null,
    'paths' => [],
    'add' => [
        EmanueleCoppola\PHPeg\App\Commands\BenchmarkCommand::class,
        EmanueleCoppola\PHPeg\App\Commands\BenchmarkCompareCommand::class,
        EmanueleCoppola\PHPeg\App\Commands\ParseCommand::class,
        EmanueleCoppola\PHPeg\App\Commands\TraceCommand::class,
        EmanueleCoppola\PHPeg\App\Commands\StepCommand::class,
    ],
    'hidden' => [],
    'remove' => [],
];
