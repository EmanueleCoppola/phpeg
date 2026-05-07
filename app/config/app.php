<?php

declare(strict_types=1);

return [
    'name' => 'PHPeg',
    'env' => env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'https://example.com'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => 'en',
    'fallback_locale' => 'en',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'providers' => [
        EmanueleCoppola\PHPeg\App\Providers\AppServiceProvider::class,
    ],
    'aliases' => [],
    'version' => trim((string) shell_exec('git describe --tags --always --dirty 2>/dev/null')) ?: 'dev',
];
