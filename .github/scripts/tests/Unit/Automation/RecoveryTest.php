<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Automation;

use DockerBase\Automation\Candidate;
use DockerBase\Automation\Merge;
use DockerBase\Automation\Recovery;
use DockerBase\Automation\RecoveryException;
use DockerBase\Automation\RecoverySelector;
use DockerBase\Automation\Tag;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecoveryTest extends TestCase
{
    #[Test]
    public function test_resumes_draft_after_publish_failure(): void
    {
        $target = str_repeat('a', 40);

        $this->assertCandidate(
            RecoverySelector::select(
                [new Tag(name: '1.4.5', target: $target)],
                [
                    $this->release(),
                    $this->release(
                        identifier: 9,
                        tag: '1.4.4',
                        draft: false,
                    ),
                ],
                [$this->merge()],
            ),
            tag: '1.4.5',
            target: $target,
            pull: 75,
            draft: 10,
        );
    }

    #[Test]
    public function test_resumes_tag_when_draft_creation_failed_and_next_run_has_no_diff(): void
    {
        $target = str_repeat('a', 40);

        $this->assertCandidate(
            RecoverySelector::select(
                [new Tag(name: '1.4.5', target: $target)],
                [
                    $this->release(
                        identifier: 9,
                        tag: '1.4.4',
                        draft: false,
                    ),
                ],
                [$this->merge()],
            ),
            tag: '1.4.5',
            target: $target,
            pull: 75,
            draft: null,
        );
    }

    #[Test]
    public function test_resumes_proven_merge_when_cancelled_before_tag_creation(): void
    {
        $target = str_repeat('d', 40);

        $this->assertCandidate(
            RecoverySelector::select(
                [
                    new Tag(
                        name: '1.4.4',
                        target: str_repeat('a', 40),
                    ),
                ],
                [$this->release(tag: '1.4.4', draft: false)],
                [$this->merge(number: 76, target: $target)],
            ),
            tag: null,
            target: $target,
            pull: 76,
            draft: null,
        );
    }

    #[Test]
    public function test_does_not_resume_merge_of_an_untested_base(): void
    {
        self::assertSame(
            null,
            RecoverySelector::select(
                [
                    new Tag(
                        name: '1.4.4',
                        target: str_repeat('a', 40),
                    ),
                ],
                [$this->release(tag: '1.4.4', draft: false)],
                [
                    $this->merge(
                        target: str_repeat('d', 40),
                        parent: str_repeat('e', 40),
                        testedBase: str_repeat('c', 40),
                    ),
                ],
            ),
        );
    }

    #[Test]
    public function test_fails_closed_for_ambiguous_proven_untagged_merges(): void
    {
        $this->expectException(RecoveryException::class);

        RecoverySelector::select(
            [
                new Tag(
                    name: '1.4.4',
                    target: str_repeat('a', 40),
                ),
            ],
            [$this->release(tag: '1.4.4', draft: false)],
            [
                $this->merge(
                    number: 76,
                    target: str_repeat('d', 40),
                ),
                $this->merge(
                    number: 77,
                    target: str_repeat('e', 40),
                ),
            ],
        );
    }

    #[Test]
    public function test_ignores_unrelated_orphan_tag(): void
    {
        self::assertSame(
            null,
            RecoverySelector::select(
                [
                    new Tag(
                        name: '9.9.9',
                        target: str_repeat('b', 40),
                    ),
                ],
                [$this->release(tag: '1.4.4', draft: false)],
                [$this->merge(body: 'No automation marker')],
            ),
        );
    }

    #[Test]
    public function test_ignores_tag_for_unmarked_or_multi_file_pull_request(): void
    {
        $target = str_repeat('a', 40);

        self::assertSame(
            null,
            RecoverySelector::select(
                [new Tag(name: '1.4.5', target: $target)],
                [$this->release(tag: '1.4.4', draft: false)],
                [
                    $this->merge(body: 'No automation marker'),
                    $this->merge(files: ['Dockerfile', 'README.md']),
                ],
            ),
        );
    }

    #[Test]
    public function test_fails_closed_for_multiple_recoverable_releases(): void
    {
        $first = str_repeat('a', 40);
        $second = str_repeat('b', 40);
        $this->expectException(RecoveryException::class);

        RecoverySelector::select(
            [
                new Tag(name: '1.4.5', target: $first),
                new Tag(name: '1.4.6', target: $second),
            ],
            [$this->release(tag: '1.4.4', draft: false)],
            [
                $this->merge(target: $first),
                $this->merge(number: 76, target: $second),
            ],
        );
    }

    #[Test]
    public function test_does_not_resume_wrong_target_draft(): void
    {
        $target = str_repeat('a', 40);
        $this->expectException(RecoveryException::class);

        RecoverySelector::select(
            [new Tag(name: '1.4.5', target: $target)],
            [
                $this->release(tag: '1.4.4', draft: false),
                $this->release(target: str_repeat('b', 40)),
            ],
            [$this->merge()],
        );
    }

    /**
     * @param list<string> $files
     */
    private function merge(
        int $number = 75,
        ?string $target = null,
        ?string $head = null,
        ?string $parent = null,
        ?string $testedBase = null,
        ?string $body = null,
        array $files = ['Dockerfile'],
    ): Merge {
        $target ??= str_repeat('a', 40);
        $head ??= str_repeat('b', 40);
        $parent ??= str_repeat('c', 40);
        $proof = $testedBase ?? $parent;

        return new Merge(
            number: $number,
            target: $target,
            head: $head,
            parents: [$parent],
            base: 'main',
            branch: 'automation/dependencies-100-1',
            body: $body ?? (
                Merge::MARKER . PHP_EOL
                . "<!-- dependency-tested-head:{$head} -->" . PHP_EOL
                . "<!-- dependency-tested-base:{$proof} -->"
            ),
            files: $files,
            state: 'merged',
        );
    }

    private function release(
        int $identifier = 10,
        string $tag = '1.4.5',
        ?string $target = null,
        int $pull = 75,
        bool $draft = true,
    ): Recovery {
        $target ??= str_repeat('a', 40);

        return new Recovery(
            identifier: $identifier,
            tag: $tag,
            target: $target,
            pull: $pull,
            draft: $draft,
            prerelease: false,
            body: Merge::MARKER . PHP_EOL
                . "<!-- dependency-target:{$target} -->" . PHP_EOL
                . "<!-- dependency-pull:{$pull} -->",
        );
    }

    private function assertCandidate(
        ?Candidate $candidate,
        ?string $tag,
        string $target,
        int $pull,
        ?int $draft,
    ): void {
        if ($candidate === null) {
            self::fail('Expected a recovery candidate');
        }

        self::assertSame($tag, $candidate->tag);
        self::assertSame($target, $candidate->target);
        self::assertSame($pull, $candidate->pull);
        self::assertSame($draft, $candidate->draft);
    }
}
