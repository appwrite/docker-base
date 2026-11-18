<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Dependency\Catalog;
use DockerBase\Dependency\Dockerfile;
use DockerBase\Dependency\Pin;
use DockerBase\Dependency\Version;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class StructureTest extends TestCase
{
    private const array CONFIGURATIONS = ['tests.yaml', 'tests-xdebug.yaml'];

    public function test_structure_tests_hold_no_version_the_updater_rewrites(): void
    {
        foreach (self::CONFIGURATIONS as $configuration) {
            $content = $this->read($configuration);
            foreach ($this->versions() as $name => $version) {
                $exact = "{$version->major}.{$version->minor}.{$version->patch}";
                foreach ([$exact, preg_quote($exact, '/')] as $spelling) {
                    self::assertStringNotContainsString(
                        $spelling,
                        $content,
                        "{$configuration} pins {$name} {$exact}, which the "
                        . 'updater only rewrites in the Dockerfile',
                    );
                }
            }
        }
    }

    /**
     * @return array<string, Version>
     */
    private function versions(): array
    {
        $catalog = Catalog::create();
        $pins = [];
        foreach ((new Dockerfile())->pins($this->read('Dockerfile'), $catalog) as $pin) {
            $pins[$pin->name] = $pin;
        }

        $versions = [];
        foreach ($catalog->dependencies() as $dependency) {
            $pin = $pins[$dependency->name] ?? null;
            self::assertInstanceOf(Pin::class, $pin);
            $version = Version::parse($pin->current);
            self::assertInstanceOf(Version::class, $version);
            $versions[$dependency->name] = $version;
        }

        return $versions;
    }

    private function read(string $file): string
    {
        $content = file_get_contents(dirname(__DIR__, 5) . "/{$file}");
        self::assertIsString($content);

        return $content;
    }
}
