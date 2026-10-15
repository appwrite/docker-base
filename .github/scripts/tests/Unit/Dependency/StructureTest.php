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

    public function test_swoole_expectation_accepts_every_release_the_updater_selects(): void
    {
        $matched = preg_match(
            "/- name: 'Swoole version'\\n(?:[ ]{4}[^\\n]*\\n)*?"
            . "[ ]{4}expectedOutput:\\n[ ]{6}- '([^'\\n]+)'\\n/",
            $this->read('tests.yaml'),
            $expectation,
        );
        self::assertSame(1, $matched, 'tests.yaml must assert the Swoole version');
        $pattern = '/' . str_replace('/', '\/', $expectation[1]) . '/';

        $version = $this->versions()['swoole'];
        $major = (int) $version->major;
        $minor = (int) $version->minor;
        $patch = (int) $version->patch;
        $line = static fn (string $release): string => 'Extension [ <persistent> '
            . "extension #46 swoole version {$release} ] {";

        foreach ([
            "{$major}.{$minor}.{$patch}",
            "{$major}.{$minor}." . ($patch + 1),
            "{$major}." . ($minor + 1) . '.0',
        ] as $release) {
            self::assertSame(
                1,
                preg_match($pattern, $line($release)),
                "Swoole {$release} must pass the structure test",
            );
        }

        foreach ([($major + 1) . '.0.0', "{$major}.{$minor}.{$patch}-dev"] as $release) {
            self::assertSame(
                0,
                preg_match($pattern, $line($release)),
                "Swoole {$release} must fail the structure test",
            );
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
