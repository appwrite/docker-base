<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Downstream;

use DockerBase\Downstream\Constants;
use DockerBase\Downstream\Exception;
use DockerBase\Downstream\Release;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Release::class)]
#[CoversClass(Constants::class)]
final class ReleaseTest extends TestCase
{
    public function test_takes_the_next_sub_version_for_the_application(): void
    {
        $release = Release::next('1.9.6', [
            'cl-1.9.0-5',
            'cl-1.9.5-1',
            'cl-1.9.6-1',
            'cl-1.9.6-2',
            'cl-usage-poc-4',
        ]);

        self::assertSame('cl-1.9.6-3', (string) $release);
        self::assertSame('1.9.6', $release->application);
        self::assertSame(3, $release->sub);
    }

    public function test_starts_at_one_for_an_unreleased_application(): void
    {
        self::assertSame(
            'cl-2.0.0-1',
            (string) Release::next('2.0.0', ['cl-1.9.6-9']),
        );
    }

    public function test_selects_the_semantic_maximum_sub_version(): void
    {
        self::assertSame(
            'cl-1.9.6-11',
            (string) Release::next('1.9.6', [
                'cl-1.9.6-9',
                'cl-1.9.6-10',
                'cl-1.9.6-2',
            ]),
        );
    }

    public function test_ignores_unrelated_and_prefixed_tags(): void
    {
        self::assertSame(
            'cl-1.9.6-1',
            (string) Release::next('1.9.6', [
                'cl-1.9.6-1-rc1',
                'cl-1.9.60-4',
                '1.9.6-7',
                'cl-shared-tables-zdt-6',
            ]),
        );
    }

    public function test_rejects_a_non_canonical_sub_version(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('non-canonical sub-version');

        Release::next('1.9.6', ['cl-1.9.6-01']);
    }

    public function test_reads_the_stable_application_version(): void
    {
        self::assertSame(
            '1.9.6',
            (new Constants())->application(
                "<?php\nconst APP_VERSION_STABLE = '1.9.6';\n",
            ),
        );
    }

    public function test_rejects_a_missing_application_version(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Expected exactly one APP_VERSION_STABLE declaration, found 0',
        );

        (new Constants())->application("<?php\n");
    }
}
