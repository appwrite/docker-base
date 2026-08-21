#!/usr/bin/env php
<?php

declare(strict_types=1);

use DockerBase\Parity\Verifier;

$root = dirname(__DIR__, 3);

require $root . '/vendor/autoload.php';

$arguments = array_slice($argv, 1);
if (count($arguments) < 1 || count($arguments) > 2) {
    fwrite(STDERR, 'Usage: parity.php RESULTS [MANIFEST]' . PHP_EOL);

    exit(2);
}

$results = $arguments[0];
$manifest = $arguments[1]
    ?? $root . '/.github/scripts/tests/equivalence.json';

try {
    $count = (new Verifier($manifest, $results))->verify();
} catch (\Throwable $exception) {
    $detail = preg_replace(
        '/\s+/',
        ' ',
        trim($exception->getMessage()),
    );
    fwrite(
        STDERR,
        'Error: '
            . ($detail === null || $detail === ''
                ? 'Parity verification failed'
                : $detail)
            . PHP_EOL,
    );

    exit(1);
}

fwrite(STDOUT, "Verified {$count} parity contracts." . PHP_EOL);
