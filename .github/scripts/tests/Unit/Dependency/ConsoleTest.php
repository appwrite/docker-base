<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Dependency\Application;
use DockerBase\Dependency\Catalog;
use DockerBase\Dependency\Console;
use DockerBase\Dependency\Dockerfile;
use DockerBase\Dependency\Reporter;
use DockerBase\Dependency\Resolver;
use DockerBase\Dependency\Selector;
use DockerBase\Dependency\Updater;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    public function test_dispatches_a_dry_run_without_mutation(): void
    {
        $directory = sys_get_temp_dir()
            . '/docker-base-console-'
            . bin2hex(random_bytes(8));
        if (! mkdir($directory)) {
            self::fail("Unable to create temporary directory: {$directory}");
        }
        $path = "{$directory}/Dockerfile";
        self::assertSame(
            strlen(Fixture::dockerfile()),
            file_put_contents($path, Fixture::dockerfile()),
        );
        $console = $this->console(
            $path,
            new Discovery(digest: Fixture::NEW_DIGEST),
        );

        try {
            $report = $console->execute(['--dry-run']);

            self::assertStringContainsString('**Updates:** 1', $report);
            self::assertSame(Fixture::dockerfile(), file_get_contents($path));
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

    public function test_rejects_unknown_arguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Unknown dependency updater argument '--unknown'",
        );

        $this->console('/tmp/Dockerfile', new Discovery())
            ->execute(['--unknown']);
    }

    private function console(
        string $path,
        Discovery $discovery,
    ): Console {
        $catalog = Catalog::create();
        $application = new Application(
            $catalog,
            new Dockerfile(),
            new Resolver($discovery, $discovery),
            new Selector(),
        );

        return new Console(
            new Updater($application),
            new Reporter(),
            $path,
        );
    }
}
