#!/usr/bin/env php
<?php

declare(strict_types=1);

use DockerBase\Automation\Clock\System as Clock;
use DockerBase\Automation\Sleeper\System as Sleeper;
use DockerBase\Automation\WorkflowOutput;
use DockerBase\Command\Process;
use DockerBase\Downstream\Application;
use DockerBase\Downstream\Constants;
use DockerBase\Downstream\Dockerfile;
use DockerBase\Downstream\Orchestrator;
use DockerBase\Downstream\Repository\GitHub;

$root = dirname(__DIR__, 3);

require $root . '/vendor/autoload.php';

$repository = getenv('DOWNSTREAM_REPOSITORY')
    ?: throw new RuntimeException('DOWNSTREAM_REPOSITORY is required');
$branch = getenv('DOWNSTREAM_BRANCH')
    ?: throw new RuntimeException('DOWNSTREAM_BRANCH is required');
$version = getenv('GITHUB_API_VERSION')
    ?: throw new RuntimeException('GITHUB_API_VERSION is required');

$application = new Application(
    new Orchestrator(
        new GitHub(new Process($root), $repository, $version),
        new Dockerfile(),
        new Constants(),
        new Clock(),
        new Sleeper(),
        $branch,
    ),
);
$output = (new WorkflowOutput(
    $application->execute(array_slice($argv, 1)),
))->render();

if ($output !== '') {
    $path = getenv('GITHUB_OUTPUT')
        ?: throw new RuntimeException('GITHUB_OUTPUT is required');
    $written = file_put_contents($path, $output, FILE_APPEND | LOCK_EX);
    if ($written !== strlen($output)) {
        throw new RuntimeException('Unable to write workflow outputs');
    }
}
