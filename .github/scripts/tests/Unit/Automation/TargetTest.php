<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Automation;

use DockerBase\Automation\Release;
use DockerBase\Automation\Tag;
use DockerBase\Automation\TargetMismatchException;
use DockerBase\Automation\TargetValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TargetTest extends TestCase
{
    #[Test]
    public function test_accepts_exact_tag_and_release_targets(): void
    {
        $this->expectNotToPerformAssertions();

        TargetValidator::validateTag(
            new Tag(name: '1.4.5', target: 'merged-head'),
            expectedName: '1.4.5',
            expectedTarget: 'merged-head',
        );
        TargetValidator::validateRelease(
            new Release(tag: '1.4.5', target: 'merged-head'),
            expectedTag: '1.4.5',
            expectedTarget: 'merged-head',
        );
    }

    #[Test]
    public function test_rejects_tag_target_mismatch(): void
    {
        $this->expectException(TargetMismatchException::class);

        TargetValidator::validateTag(
            new Tag(name: '1.4.5', target: 'wrong-head'),
            expectedName: '1.4.5',
            expectedTarget: 'merged-head',
        );
    }

    #[Test]
    public function test_rejects_release_target_mismatch(): void
    {
        $this->expectException(TargetMismatchException::class);

        TargetValidator::validateRelease(
            new Release(tag: '1.4.5', target: 'wrong-head'),
            expectedTag: '1.4.5',
            expectedTarget: 'merged-head',
        );
    }
}
