#!/usr/bin/env php
<?php

declare(strict_types=1);

use DockerBase\Command\Process;
use DockerBase\Dependency\Application;
use DockerBase\Dependency\Console;
use DockerBase\Dependency\Fetcher\HTTP;
use DockerBase\Dependency\Program;
use DockerBase\Dependency\Reporter;
use DockerBase\Dependency\Updater;

$root = dirname(__DIR__, 3);

require $root . '/vendor/autoload.php';

$result = (new Program(
    new Console(
        new Updater(
            Application::create(
                new Process($root),
                new HTTP(),
            ),
        ),
        new Reporter(),
        $root . '/Dockerfile',
    ),
))->execute(array_slice($argv, 1));

if ($result->output !== '') {
    fwrite(STDOUT, $result->output);
}
if ($result->error !== '') {
    fwrite(STDERR, $result->error);
}

exit($result->code);
