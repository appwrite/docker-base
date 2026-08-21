<?php

declare(strict_types=1);

use DockerBase\Dependency\Application;
use DockerBase\Dependency\Catalog;
use DockerBase\Dependency\Console;
use DockerBase\Dependency\Dockerfile;
use DockerBase\Dependency\Program;
use DockerBase\Dependency\Reporter;
use DockerBase\Dependency\Resolver;
use DockerBase\Dependency\Selector;
use DockerBase\Dependency\Updater;
use DockerBase\Tests\Unit\Dependency\Discovery;

$root = dirname(__DIR__, 6);

require $root . '/vendor/autoload.php';

$values = $_SERVER['argv'] ?? [];
if (! is_array($values)) {
    $values = [];
}
$arguments = [];
foreach ($values as $value) {
    if (is_string($value)) {
        $arguments[] = $value;
    }
}

$discovery = new Discovery();
$result = (new Program(
    new Console(
        new Updater(
            new Application(
                Catalog::create(),
                new Dockerfile(),
                new Resolver($discovery, $discovery),
                new Selector(),
            ),
        ),
        new Reporter(),
        $arguments[1] ?? '',
    ),
))->execute(array_slice($arguments, 2));

if ($result->output !== '') {
    fwrite(STDOUT, $result->output);
}
if ($result->error !== '') {
    fwrite(STDERR, $result->error);
}

exit($result->code);
