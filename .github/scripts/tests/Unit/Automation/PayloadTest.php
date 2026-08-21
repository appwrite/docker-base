<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Automation;

use DockerBase\Automation\Pull\Payload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PayloadTest extends TestCase
{
    public function test_validates_the_exact_open_pull_request(): void
    {
        self::assertSame(
            [
                'number' => '75',
                'base' => str_repeat('b', 40),
            ],
            Payload::validate(
                $this->payload(),
                'automation/dependencies-100-1',
                str_repeat('a', 40),
                str_repeat('b', 40),
            ),
        );
    }

    /**
     * @param array<string, mixed> $change
     */
    #[DataProvider('invalid')]
    public function test_rejects_an_unproven_pull_request(
        array $change,
        string $message,
    ): void {
        $payload = json_decode(
            $this->payload(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($payload);
        foreach ($change as $name => $value) {
            $payload[$name] = $value;
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        Payload::validate(
            json_encode($payload, JSON_THROW_ON_ERROR),
            'automation/dependencies-100-1',
            str_repeat('a', 40),
            str_repeat('b', 40),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'number must be a positive integer' => [
            ['number' => '75'],
            'Pull request number is invalid',
        ];
        yield 'base branch must be main' => [
            ['baseRefName' => 'release'],
            'Pull request does not target main',
        ];
        yield 'base oid must be tested' => [
            ['baseRefOid' => str_repeat('c', 40)],
            'Pull request does not use the tested base',
        ];
        yield 'head branch must be pushed' => [
            ['headRefName' => 'other'],
            'Pull request does not use the pushed head',
        ];
        yield 'head oid must be pushed' => [
            ['headRefOid' => str_repeat('c', 40)],
            'Pull request does not use the pushed head',
        ];
        yield 'state must be open' => [
            ['state' => 'CLOSED'],
            'Pull request is not open',
        ];
    }

    private function payload(): string
    {
        return json_encode(
            [
                'baseRefName' => 'main',
                'baseRefOid' => str_repeat('b', 40),
                'createdAt' => '2026-07-24T08:00:00Z',
                'headRefName' => 'automation/dependencies-100-1',
                'headRefOid' => str_repeat('a', 40),
                'number' => 75,
                'state' => 'OPEN',
            ],
            JSON_THROW_ON_ERROR,
        );
    }
}
