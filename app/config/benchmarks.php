<?php

declare(strict_types=1);

return [
    'results_directory' => dirname(__DIR__, 2) . '/benchmarks/results',
    'cases' => [
        EmanueleCoppola\PHPeg\App\Benchmarks\Cases\LargeArithmeticBenchmark::class,
        EmanueleCoppola\PHPeg\App\Benchmarks\Cases\DeepNestedRecursionBenchmark::class,
        EmanueleCoppola\PHPeg\App\Benchmarks\Cases\LargeJsonLikeBenchmark::class,
        EmanueleCoppola\PHPeg\App\Benchmarks\Cases\BacktrackingHeavyBenchmark::class,
        EmanueleCoppola\PHPeg\App\Benchmarks\Cases\NamedCaptureAndSpanChecksBenchmark::class,
        EmanueleCoppola\PHPeg\App\Benchmarks\Cases\LakeIslandBenchmark::class,
        EmanueleCoppola\PHPeg\App\Benchmarks\Cases\ManualWaterIslandBenchmark::class,
        EmanueleCoppola\PHPeg\App\Benchmarks\Cases\AnnotatedWaterIslandBenchmark::class,
    ],
];
