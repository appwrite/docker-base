#!/usr/bin/env php
<?php

declare(strict_types=1);

use DockerBase\Automation\Application;
use DockerBase\Automation\Clock\System as Clock;
use DockerBase\Automation\Orchestrator;
use DockerBase\Automation\Repository\GitHub;
use DockerBase\Automation\Sleeper\System as Sleeper;
use DockerBase\Automation\WorkflowOutput;
use DockerBase\Command\Process;

$root = dirname(__DIR__, 3);

require $root . '/vendor/autoload.php';

$repository = getenv('GITHUB_REPOSITORY')
    ?: throw new RuntimeException('GITHUB_REPOSITORY is required');
$version = getenv('GITHUB_API_VERSION')
    ?: throw new RuntimeException('GITHUB_API_VERSION is required');
$input = stream_get_contents(STDIN);
if ($input === false) {
    throw new RuntimeException('Unable to read standard input');
}

$application = new Application(
    new Orchestrator(
        new GitHub(new Process($root), $repository, $version),
        new Clock(),
        new Sleeper(),
    ),
);
$output = (new WorkflowOutput(
    $application->execute(array_slice($argv, 1), $input),
))->render();

if ($output !== '') {
    $path = getenv('GITHUB_OUTPUT')
        ?: throw new RuntimeException('GITHUB_OUTPUT is required');
    $written = file_put_contents($path, $output, FILE_APPEND | LOCK_EX);
    if ($written !== strlen($output)) {
        throw new RuntimeException('Unable to write workflow outputs');
    }
}
