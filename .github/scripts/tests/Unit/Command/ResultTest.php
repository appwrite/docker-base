<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Command;

use DockerBase\Command\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Result::class)]
final class ResultTest extends TestCase
{
    public function testReportsWhetherTheCommandSucceeded(): void
    {
        self::assertTrue((new Result(0, 'output', ''))->succeeded());
        self::assertFalse((new Result(1, '', 'error'))->succeeded());
    }
}
