<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Command\Result;
use DockerBase\Command\Runner;
use DockerBase\Dependency\Catalog;
use DockerBase\Dependency\Fetcher;
use DockerBase\Dependency\Source\Git;
use LogicException;
use Override;

final class Discovery implements Fetcher, Runner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<string> */
    public array $urls = [];

    /**
     * @param array<string, list<string>> $releases
     * @param list<array{string, string}> $pecl
     */
    public function __construct(
        private readonly string $digest = Fixture::OLD_DIGEST,
        private readonly array $releases = [],
        private readonly array $pecl = [],
        private readonly ?string $digestOutput = null,
    ) {
    }

    /**
     * @param list<string> $command
     */
    #[Override]
    public function run(array $command, bool $check = true): Result
    {
        $this->commands[] = $command;
        if ($command === [
            'docker',
            'buildx',
            'imagetools',
            'inspect',
            Catalog::BASE,
        ]) {
            $output = $this->digestOutput
                ?? "Name: docker.io/library/" . Catalog::BASE
                    . "\nDigest: {$this->digest}\n";

            return new Result(0, $output, '');
        }

        if (array_slice($command, 0, 4) === [
            'git',
            'ls-remote',
            '--tags',
            '--refs',
        ]) {
            $url = $command[4];
            foreach (Catalog::create()->dependencies() as $dependency) {
                if (
                    $dependency->source instanceof Git
                    && $dependency->source->url() === $url
                ) {
                    $spellings = $this->releases[$dependency->name]
                        ?? [Fixture::CURRENT[$dependency->name]];

                    return new Result(
                        0,
                        Fixture::gitTags(...$spellings),
                        '',
                    );
                }
            }
        }

        throw new LogicException(
            'Unexpected command: ' . implode(' ', $command),
        );
    }

    #[Override]
    public function fetch(string $url): string
    {
        $this->urls[] = $url;
        if ($url !== Catalog::PECL_RELEASES) {
            throw new LogicException("Unexpected URL: {$url}");
        }

        $pecl = $this->pecl === []
            ? [[Fixture::CURRENT['protobuf'], 'stable']]
            : $this->pecl;

        return Fixture::peclReleases(...$pecl);
    }
}
