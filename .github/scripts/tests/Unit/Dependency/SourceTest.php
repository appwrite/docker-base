<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Dependency\Source;
use DockerBase\Dependency\Source\Git;
use DockerBase\Dependency\Source\PECL;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Git::class)]
#[CoversClass(PECL::class)]
final class SourceTest extends TestCase
{
    public function testExposesTypedSourceURLs(): void
    {
        $git = new Git('https://github.com/appwrite/docker-base.git');
        $pecl = new PECL('https://pecl.php.net/rest/r/allreleases.xml');

        self::assertInstanceOf(Source::class, $git);
        self::assertInstanceOf(Source::class, $pecl);
        self::assertSame(
            'https://github.com/appwrite/docker-base.git',
            $git->url(),
        );
        self::assertSame(
            'https://pecl.php.net/rest/r/allreleases.xml',
            $pecl->url(),
        );
    }
}
