<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Dependency\Application;
use DockerBase\Dependency\Catalog;
use DockerBase\Dependency\Dependency;
use DockerBase\Dependency\Dockerfile;
use DockerBase\Dependency\Exception;
use DockerBase\Dependency\Pin;
use DockerBase\Dependency\Resolver;
use DockerBase\Dependency\Selector;
use DockerBase\Dependency\Source\Git;
use DockerBase\Dependency\Updater;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(Dockerfile::class)]
#[CoversClass(Resolver::class)]
#[CoversClass(Updater::class)]
final class DockerfileTest extends TestCase
{
    public function test_reads_every_expected_declaration_once(): void
    {
        $pins = (new Dockerfile())->pins(
            Fixture::dockerfile(),
            Catalog::create(),
        );
        $names = array_map(
            static fn (Pin $pin): string => $pin->name,
            array_slice($pins, 1),
        );
        $expected = array_keys(Fixture::CURRENT);
        sort($names, SORT_STRING);
        sort($expected, SORT_STRING);

        self::assertSame(14, count($pins));
        self::assertSame(Catalog::BASE, $pins[0]->name);
        self::assertSame(Fixture::OLD_DIGEST, $pins[0]->current);
        self::assertSame($expected, $names);
    }

    public function test_real_dockerfile_declarations_match_independent_contract(): void
    {
        $path = dirname(__DIR__, 5) . '/Dockerfile';
        $content = file_get_contents($path);
        self::assertSame(true, is_string($content));

        $count = preg_match_all(
            '/^[ \t]*(?:(?:ARG|ENV)[ \t]+)?'
            . '((?:BASE_IMAGE|PHP_[A-Z0-9_]+_VERSION))[ \t]*=/m',
            $content,
            $matches,
        );
        self::assertSame(14, $count);

        $declarations = array_values(array_unique($matches[1]));
        $expectedDeclarations = Fixture::EXPECTED_DOCKERFILE_DECLARATIONS;
        sort($declarations, SORT_STRING);
        sort($expectedDeclarations, SORT_STRING);
        self::assertSame($expectedDeclarations, $declarations);

        $pins = (new Dockerfile())->pins($content, Catalog::create());
        $names = array_map(
            static fn (Pin $pin): string => $pin->name,
            $pins,
        );
        $expectedNames = [Catalog::BASE, ...array_keys(Fixture::CURRENT)];
        sort($names, SORT_STRING);
        sort($expectedNames, SORT_STRING);
        self::assertSame($expectedNames, $names);
        self::assertSame(
            1,
            preg_match('/\Asha256:[0-9a-f]{64}\z/', $pins[0]->current),
        );
    }

    public function test_rejects_unknown_extension_declaration(): void
    {
        $this->assertFailure(
            str_replace(
                "ENV \\\n",
                "ENV \\\n    PHP_UNKNOWN_VERSION=\"1.2.3\" \\\n",
                Fixture::dockerfile(),
            ),
            'Unknown PHP extension version declaration: PHP_UNKNOWN_VERSION',
        );

        $this->assertFailure(
            Fixture::dockerfile()
                . "\nENV PHP_second_VERSION=\"1.2.3\" "
                . "PHP_THIRD_VERSION=\"2.3.4\"\n",
            'Unknown PHP extension version declarations: '
                . 'PHP_THIRD_VERSION, PHP_second_VERSION',
        );

        $this->assertFailure(
            Fixture::dockerfile() . "\nARG PHP_ARGUMENT_VERSION\n",
            'Unknown PHP extension version declaration: PHP_ARGUMENT_VERSION',
        );
    }

    public function test_rejects_missing_declaration(): void
    {
        $content = str_replace(
            '    PHP_REDIS_VERSION="' . Fixture::CURRENT['redis'] . "\" \\\n",
            '',
            Fixture::dockerfile(),
        );

        $this->assertFailure(
            $content,
            'Expected exactly one PHP_REDIS_VERSION declaration, found 0',
        );
    }

    public function test_rejects_duplicate_declaration(): void
    {
        $content = Fixture::dockerfile()
            . "\nENV PHP_REDIS_VERSION=\""
            . Fixture::CURRENT['redis']
            . "\"\n";

        $this->assertFailure(
            $content,
            'Expected exactly one PHP_REDIS_VERSION declaration, found 2',
        );
    }

    public function test_rejects_missing_and_duplicate_base_declarations(): void
    {
        $declaration = 'ARG BASE_IMAGE="' . Catalog::BASE . '@'
            . Fixture::OLD_DIGEST . "\"\n";
        $missing = str_replace(
            $declaration,
            '',
            Fixture::dockerfile(),
        );
        $this->assertFailure(
            $missing,
            'Expected exactly one ARG BASE_IMAGE declaration, found 0',
        );

        $this->assertFailure(
            Fixture::dockerfile() . $declaration,
            'Expected exactly one ARG BASE_IMAGE declaration, found 2',
        );
    }

    public function test_rejects_invalid_pin_spelling(): void
    {
        $content = str_replace(
            'PHP_YAML_VERSION="' . Fixture::CURRENT['yaml'] . '"',
            'PHP_YAML_VERSION="2.4.0RC1"',
            Fixture::dockerfile(),
        );

        $this->assertFailure(
            $content,
            'PHP_YAML_VERSION must be an exact stable '
                . 'v?MAJOR.MINOR.PATCH version',
        );
    }

    public function test_rejects_invalid_base_digest(): void
    {
        $content = str_replace(
            Fixture::OLD_DIGEST,
            'sha256:ABC',
            Fixture::dockerfile(),
        );

        $this->assertFailure(
            $content,
            'ARG BASE_IMAGE must pin ' . Catalog::BASE
                . ' to a lowercase sha256 digest',
        );
    }

    public function test_resolves_lowercase_multiarch_digest_through_buildx(): void
    {
        $discovery = new Discovery(digest: Fixture::NEW_DIGEST);
        $resolver = new Resolver($discovery, $discovery);

        self::assertSame(Fixture::NEW_DIGEST, $resolver->digest());
        self::assertSame(
            [[
                'docker',
                'buildx',
                'imagetools',
                'inspect',
                Catalog::BASE,
            ]],
            $discovery->commands,
        );
    }

    public function test_rejects_invalid_or_ambiguous_digest_output(): void
    {
        foreach ([
            'Digest: sha256:' . str_repeat('A', 64) . "\n",
            'Digest: ' . Fixture::OLD_DIGEST . "\n"
                . 'Digest: ' . Fixture::NEW_DIGEST . "\n",
        ] as $output) {
            $discovery = new Discovery(digestOutput: $output);
            $resolver = new Resolver($discovery, $discovery);

            try {
                $resolver->digest();
                self::fail('Expected invalid digest output to fail');
            } catch (Exception $exception) {
                self::assertSame(
                    'Expected one lowercase sha256 digest for '
                        . Catalog::BASE,
                    $exception->getMessage(),
                );
            }
        }
    }

    public function test_plans_updates_without_touching_references(): void
    {
        $discovery = new Discovery(
            digest: Fixture::NEW_DIGEST,
            releases: [
                'redis' => [
                    Fixture::CURRENT['redis'],
                    '6.3.1',
                    'v6.3.1',
                    '7.0.0',
                ],
                'swoole' => [
                    Fixture::CURRENT['swoole'],
                    '6.2.1',
                    'v6.2.1',
                ],
            ],
            pecl: [
                [Fixture::CURRENT['protobuf'], 'stable'],
                ['5.34.1', 'stable'],
                ['5.35.0RC1', 'beta'],
                ['6.0.0', 'stable'],
            ],
        );
        $plan = $this->application($discovery)->plan(
            Fixture::dockerfile(),
        );

        self::assertSame(true, $plan->changed());
        self::assertSame(
            true,
            str_contains(
                $plan->content,
                'ARG BASE_IMAGE="' . Catalog::BASE . '@'
                    . Fixture::NEW_DIGEST . '"',
            ),
        );
        self::assertSame(
            true,
            str_contains($plan->content, 'PHP_REDIS_VERSION="6.3.1"'),
        );
        self::assertSame(
            true,
            str_contains($plan->content, 'PHP_PROTOBUF_VERSION="5.34.1"'),
        );
        self::assertSame(
            true,
            str_contains($plan->content, 'PHP_SWOOLE_VERSION="v6.2.1"'),
        );
        self::assertSame(
            true,
            str_contains($plan->content, 'RUN echo "$PHP_REDIS_VERSION"'),
        );
    }

    public function test_noop_plan_preserves_content_exactly(): void
    {
        $content = Fixture::dockerfile();
        $plan = $this->application(new Discovery())->plan($content);

        self::assertSame(false, $plan->changed());
        self::assertSame($content, $plan->content);
    }

    public function test_injects_every_external_interaction(): void
    {
        $discovery = new Discovery();
        $catalog = Catalog::create();
        $this->application($discovery, $catalog)->plan(
            Fixture::dockerfile(),
        );
        $gitSources = count(array_filter(
            $catalog->dependencies(),
            static fn (Dependency $dependency): bool => $dependency->source instanceof Git,
        ));

        self::assertSame(1 + $gitSources, count($discovery->commands));
        self::assertSame([Catalog::PECL_RELEASES], $discovery->urls);

        foreach ($catalog->dependencies() as $dependency) {
            if (! $dependency->source instanceof Git) {
                continue;
            }

            self::assertSame(
                true,
                in_array(
                    [
                        'git',
                        'ls-remote',
                        '--tags',
                        '--refs',
                        $dependency->source->url(),
                    ],
                    $discovery->commands,
                    true,
                ),
            );
        }
    }

    public function test_dry_run_does_not_mutate_dockerfile_or_siblings(): void
    {
        $discovery = new Discovery(
            digest: Fixture::NEW_DIGEST,
            releases: [
                'redis' => [Fixture::CURRENT['redis'], '6.3.1'],
            ],
        );
        $directory = $this->directory();
        $path = "{$directory}/Dockerfile";
        $sibling = "{$directory}/keep.txt";
        $original = Fixture::dockerfile();
        file_put_contents($path, $original);
        file_put_contents($sibling, 'unchanged');

        try {
            $plan = (new Updater($this->application($discovery)))
                ->update($path, true);

            self::assertSame(true, $plan->changed());
            self::assertSame($original, file_get_contents($path));
            self::assertSame('unchanged', file_get_contents($sibling));
        } finally {
            unlink($path);
            unlink($sibling);
            rmdir($directory);
        }
    }

    public function test_update_mutates_only_dockerfile(): void
    {
        $directory = $this->directory();
        $path = "{$directory}/Dockerfile";
        $sibling = "{$directory}/keep.txt";
        file_put_contents($path, Fixture::dockerfile());
        file_put_contents($sibling, 'unchanged');

        try {
            $plan = (new Updater($this->application(
                new Discovery(digest: Fixture::NEW_DIGEST),
            )))->update($path);

            self::assertSame($plan->content, file_get_contents($path));
            self::assertSame('unchanged', file_get_contents($sibling));
        } finally {
            unlink($path);
            unlink($sibling);
            rmdir($directory);
        }
    }

    public function test_rejects_non_dockerfile_target(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The update target must be named Dockerfile',
        );

        (new Updater($this->application(new Discovery())))
            ->update('/tmp/not-a-dockerfile');
    }

    private function application(
        Discovery $discovery,
        ?Catalog $catalog = null,
    ): Application {
        $catalog ??= Catalog::create();

        return new Application(
            $catalog,
            new Dockerfile(),
            new Resolver($discovery, $discovery),
            new Selector(),
        );
    }

    private function assertFailure(string $content, string $message): void
    {
        try {
            (new Dockerfile())->pins($content, Catalog::create());
            self::fail('Expected Dockerfile validation to fail');
        } catch (Exception $exception) {
            self::assertSame($message, $exception->getMessage());
        }
    }

    private function directory(): string
    {
        $path = sys_get_temp_dir()
            . '/docker-base-dependencies-'
            . bin2hex(random_bytes(8));
        if (! mkdir($path)) {
            self::fail("Unable to create temporary directory: {$path}");
        }

        return $path;
    }
}
