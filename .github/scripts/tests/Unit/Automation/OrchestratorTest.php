<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Automation;

use DateTimeImmutable;
use DockerBase\Automation\Clock;
use DockerBase\Automation\HeadChangedException;
use DockerBase\Automation\Merge;
use DockerBase\Automation\MergeResult;
use DockerBase\Automation\Orchestrator;
use DockerBase\Automation\Preparation;
use DockerBase\Automation\PullRequest;
use DockerBase\Automation\PullRequestUnavailableException;
use DockerBase\Automation\Recovery;
use DockerBase\Automation\Repository;
use DockerBase\Automation\Repository\GitHub;
use DockerBase\Automation\ReviewDecision;
use DockerBase\Automation\Run;
use DockerBase\Automation\Sleeper;
use DockerBase\Automation\Tag;
use DockerBase\Automation\TargetMismatchException;
use DockerBase\Command\Result;
use DockerBase\Tests\Support\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(GitHub::class)]
#[CoversClass(Orchestrator::class)]
final class OrchestratorTest extends TestCase
{
    public function test_handles_missing_release_by_tag_as_none(): void
    {
        $runner = new Queue([
            $this->commandResult(1, '{"status":"404"}'),
            $this->commandResult(
                output: '{"data":{"repository":{"release":null}}}',
            ),
        ]);

        self::assertNull($this->github($runner)->releaseByTag('1.4.5'));
        self::assertSame(0, $runner->remaining());
        self::assertSame(
            'repos/appwrite/docker-base/releases/tags/1.4.5',
            $runner->commands()[0]['command'][4],
        );
        self::assertSame('graphql', $runner->commands()[1]['command'][4]);
    }

    public function test_finds_draft_after_release_by_tag_returns_404(): void
    {
        $target = str_repeat('a', 40);
        $runner = new Queue([
            $this->commandResult(1, '{"status":"404"}'),
            $this->commandResult(
                output: json_encode(
                    [
                        'data' => [
                            'repository' => [
                                'release' => [
                                    'databaseId' => 10,
                                    'tagName' => '1.4.5',
                                    'isDraft' => true,
                                    'isPrerelease' => false,
                                    'description' => '',
                                    'tagCommit' => ['oid' => $target],
                                ],
                            ],
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        $draft = $this->github($runner)->releaseByTag('1.4.5');

        self::assertNotNull($draft);
        self::assertSame(10, $draft->identifier);
        self::assertSame('1.4.5', $draft->tag);
        self::assertSame($target, $draft->target);
        self::assertTrue($draft->draft);
        self::assertSame(0, $runner->remaining());
    }

    public function test_exact_lookup_finds_published_release_omitted_from_list(): void
    {
        $first = str_repeat('a', 40);
        $second = str_repeat('b', 40);
        $runner = new Queue([
            $this->commandResult(
                output: $this->pages([
                    [
                        'ref' => 'refs/tags/1.4.3',
                        'object' => ['type' => 'commit', 'sha' => $first],
                    ],
                    [
                        'ref' => 'refs/tags/1.4.4',
                        'object' => ['type' => 'commit', 'sha' => $second],
                    ],
                ]),
            ),
            $this->commandResult(
                output: $this->pages([
                    $this->release(
                        identifier: 9,
                        tag: '1.4.3',
                        target: $first,
                        draft: false,
                    ),
                ]),
            ),
            $this->commandResult(
                output: json_encode(
                    $this->release(
                        identifier: 9,
                        tag: '1.4.3',
                        target: $first,
                        draft: false,
                    ),
                    JSON_THROW_ON_ERROR,
                ),
            ),
            $this->commandResult(
                output: json_encode(
                    $this->release(
                        identifier: 10,
                        tag: '1.4.4',
                        target: $second,
                        draft: false,
                    ),
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        $releases = $this->github($runner)->releases();

        self::assertSame(
            ['1.4.3', '1.4.4'],
            array_map(
                static fn (Recovery $release): string => $release->tag,
                $releases,
            ),
        );
        self::assertSame(0, $runner->remaining());
        self::assertStringEndsWith(
            '/releases/tags/1.4.3',
            $runner->commands()[2]['command'][4],
        );
        self::assertStringEndsWith(
            '/releases/tags/1.4.4',
            $runner->commands()[3]['command'][4],
        );
    }

    public function test_recovers_merge_cancelled_before_tag_on_next_no_diff_run(): void
    {
        $released = str_repeat('a', 40);
        $target = str_repeat('b', 40);
        $head = str_repeat('c', 40);
        $base = str_repeat('d', 40);
        $repository = $this->createStub(Repository::class);
        $repository->method('tags')->willReturn([
            new Tag('1.4.4', $released),
        ]);
        $repository->method('releases')->willReturn([
            new Recovery(
                9,
                '1.4.4',
                $released,
                0,
                false,
                false,
                '',
            ),
        ]);
        $repository->method('mergedPullRequests')->willReturn([
            new Merge(
                number: 75,
                target: $target,
                head: $head,
                parents: [$base],
                base: 'main',
                branch: 'automation/dependencies-100-1',
                body: $this->pullBody($head, $base),
                files: ['Dockerfile'],
                state: 'merged',
            ),
        ]);

        $candidate = $this->orchestrator($repository)->recover();

        self::assertNotNull($candidate);
        self::assertNull($candidate->tag);
        self::assertSame($target, $candidate->target);
        self::assertSame(75, $candidate->pull);
        self::assertNull($candidate->draft);
    }

    public function test_resolves_recovery_commit_when_rest_merge_sha_is_null(): void
    {
        $head = str_repeat('a', 40);
        $base = str_repeat('b', 40);
        $commit = str_repeat('c', 40);
        $body = $this->pullBody($head, $base);
        $runner = new Queue([
            $this->commandResult(
                output: $this->pages([
                    [
                        'number' => 75,
                        'merged_at' => '2026-07-24T00:00:00Z',
                        'merge_commit_sha' => null,
                        'head' => [
                            'ref' => 'automation/dependencies-100-1',
                            'sha' => $head,
                        ],
                        'base' => ['ref' => 'main'],
                        'body' => $body,
                    ],
                ]),
            ),
            $this->commandResult(
                output: json_encode(
                    $this->pull(
                        $head,
                        $base,
                        'MERGED',
                        $commit,
                    ),
                    JSON_THROW_ON_ERROR,
                ),
            ),
            $this->commandResult(
                output: $this->pages([['filename' => 'Dockerfile']]),
            ),
            $this->commandResult(
                output: json_encode(
                    ['parents' => [['sha' => $base]]],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        $merges = $this->github($runner)->mergedPullRequests();

        self::assertCount(1, $merges);
        self::assertSame(75, $merges[0]->number);
        self::assertSame($commit, $merges[0]->target);
        self::assertSame($head, $merges[0]->head);
        self::assertSame([$base], $merges[0]->parents);
        self::assertSame(['Dockerfile'], $merges[0]->files);
        self::assertSame(0, $runner->remaining());
    }

    public function test_accepts_nonzero_merge_only_after_exact_remote_proof(): void
    {
        $head = str_repeat('a', 40);
        $base = str_repeat('b', 40);
        $commit = str_repeat('c', 40);
        $runner = new Queue([
            $this->commandResult(
                output: json_encode(
                    $this->pull($head, $base),
                    JSON_THROW_ON_ERROR,
                ),
            ),
            $this->commandResult(1),
            $this->commandResult(
                output: json_encode(
                    $this->pull($head, $base, 'MERGED', $commit),
                    JSON_THROW_ON_ERROR,
                ),
            ),
            $this->commandResult(
                output: json_encode(
                    ['parents' => [['sha' => $base]]],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        $merged = $this->orchestrator(
            $this->github($runner),
        )->merge(75, $head, $base);

        self::assertSame($commit, $merged);
        self::assertFalse($runner->commands()[1]['check']);
        self::assertSame(0, $runner->remaining());
    }

    public function test_rejects_nonzero_merge_without_merged_remote_state(): void
    {
        $head = str_repeat('a', 40);
        $base = str_repeat('b', 40);
        $runner = new Queue([
            $this->commandResult(
                output: json_encode(
                    $this->pull($head, $base),
                    JSON_THROW_ON_ERROR,
                ),
            ),
            $this->commandResult(1),
            $this->commandResult(
                output: json_encode(
                    $this->pull($head, $base),
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        $this->expectException(PullRequestUnavailableException::class);
        $this->orchestrator(
            $this->github($runner),
        )->merge(75, $head, $base);
    }

    public function test_rejects_merge_when_squash_parent_is_not_tested_base(): void
    {
        $head = str_repeat('a', 40);
        $base = str_repeat('b', 40);
        $commit = str_repeat('c', 40);
        $runner = new Queue([
            $this->commandResult(
                output: json_encode(
                    $this->pull($head, $base),
                    JSON_THROW_ON_ERROR,
                ),
            ),
            $this->commandResult(),
            $this->commandResult(
                output: json_encode(
                    $this->pull($head, $base, 'MERGED', $commit),
                    JSON_THROW_ON_ERROR,
                ),
            ),
            $this->commandResult(
                output: json_encode(
                    ['parents' => [['sha' => str_repeat('d', 40)]]],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        $this->expectException(HeadChangedException::class);
        $this->orchestrator(
            $this->github($runner),
        )->merge(75, $head, $base);
    }

    public function test_existing_published_release_prevents_duplicate_draft(): void
    {
        $target = str_repeat('a', 40);
        $runner = new Queue([
            $this->commandResult(
                output: json_encode(
                    $this->release(
                        identifier: 10,
                        tag: '1.4.5',
                        target: $target,
                        draft: false,
                    ),
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        try {
            $this->github($runner)->createDraft(
                '1.4.5',
                $target,
                75,
                $this->draftBody($target, 75),
            );
            self::fail('A published release must prevent draft creation');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString(
                'already published',
                $exception->getMessage(),
            );
        }

        self::assertCount(1, $runner->commands());
        self::assertSame(0, $runner->remaining());
    }

    public function test_existing_exact_draft_avoids_duplicate_create(): void
    {
        $target = str_repeat('a', 40);
        $body = $this->draftBody($target, 75);
        $runner = new Queue([
            $this->commandResult(
                output: json_encode(
                    $this->release(
                        identifier: 10,
                        tag: '1.4.5',
                        target: $target,
                        draft: true,
                        body: $body,
                    ),
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        $draft = $this->github($runner)->createDraft(
            '1.4.5',
            $target,
            75,
            $body,
        );

        self::assertSame(10, $draft->identifier);
        self::assertCount(1, $runner->commands());
        self::assertSame(0, $runner->remaining());
    }

    public function test_recovers_concurrently_created_draft_after_422(): void
    {
        $target = str_repeat('a', 40);
        $body = $this->draftBody($target, 75);
        $runner = new Queue([
            $this->commandResult(1, '{"status":"404"}'),
            $this->commandResult(
                output: '{"data":{"repository":{"release":null}}}',
            ),
            $this->commandResult(1, '{"status":"422"}'),
            $this->commandResult(
                output: json_encode(
                    $this->release(
                        identifier: 10,
                        tag: '1.4.5',
                        target: $target,
                        draft: true,
                        body: $body,
                    ),
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        $draft = $this->github($runner)->createDraft(
            '1.4.5',
            $target,
            75,
            $body,
        );

        self::assertSame(10, $draft->identifier);
        self::assertSame(0, $runner->remaining());
    }

    public function test_does_not_publish_when_prepublication_target_changed(): void
    {
        $repository = $this->repository();
        $repository->method('tag')->willReturn(
            new Tag('1.4.5', 'wrong'),
        );
        $repository->expects(self::never())->method('publish');

        $this->expectException(TargetMismatchException::class);
        $this->orchestrator($repository)->publish(
            '1.4.5',
            'expected',
            75,
            10,
        );
    }

    public function test_returns_release_to_draft_when_postpublication_target_changed(): void
    {
        $target = 'expected';
        $body = $this->draftBody($target, 75);
        $preparation = new Preparation('1.4.5', $target, 75, 10);
        $repository = $this->repository();
        $repository->method('tag')->willReturnOnConsecutiveCalls(
            new Tag('1.4.5', $target),
            new Tag('1.4.5', 'wrong'),
        );
        $repository->method('draft')->willReturn(
            new Recovery(
                10,
                '1.4.5',
                $target,
                75,
                true,
                false,
                $body,
            ),
        );
        $repository->method('publish')->with(
            self::equalTo($preparation),
        )->willReturn(
            new Recovery(
                10,
                '1.4.5',
                $target,
                75,
                false,
                false,
                $body,
            ),
        );
        $repository->expects(self::once())->method('redraft')->with(
            self::equalTo($preparation),
        )->willReturn(
            new Recovery(
                10,
                '1.4.5',
                $target,
                75,
                true,
                false,
                $body,
            ),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('returned to draft');
        $this->orchestrator($repository)->publish(
            '1.4.5',
            $target,
            75,
            10,
        );
    }

    public function test_waits_for_the_four_exact_current_head_workflows(): void
    {
        $head = str_repeat('a', 40);
        $branch = 'automation/dependencies-100-1';
        $created = new DateTimeImmutable('2026-07-24T08:00:00+00:00');
        $expected = [
            ['build-and-push.yml', 'Build and Push', 'push'],
            ['dive.yml', 'Dive Test', 'push'],
            [
                'structure-test.yml',
                'Container Structure Test',
                'push',
            ],
            ['trivy.yml', 'Trivy Scan', 'pull_request'],
        ];
        $actual = [];
        $repository = $this->repository();
        $repository->expects(self::exactly(4))
            ->method('runs')
            ->willReturnCallback(
                static function (
                    string $filename,
                    string $event,
                    string $runHead,
                    string $runBranch,
                ) use (
                    &$actual,
                    $expected,
                    $head,
                    $branch,
                    $created,
                ): array {
                    $index = count($actual);
                    [$expectedFilename, $workflow, $expectedEvent] =
                        $expected[$index];
                    $actual[] = [$filename, $workflow, $event];
                    self::assertSame($expectedFilename, $filename);
                    self::assertSame($expectedEvent, $event);
                    self::assertSame($head, $runHead);
                    self::assertSame($branch, $runBranch);

                    return [
                        new Run(
                            $index + 1,
                            $workflow,
                            $event,
                            $head,
                            $branch,
                            $created,
                            1,
                            'completed',
                            'success',
                        ),
                    ];
                },
            );
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn($created);
        $sleeper = $this->createMock(Sleeper::class);
        $sleeper->expects(self::never())->method('sleep');

        (new Orchestrator($repository, $clock, $sleeper))->checks(
            $branch,
            $head,
            '2026-07-24T08:00:00Z',
        );

        self::assertSame($expected, $actual);
    }

    public function test_rejects_a_non_utc_workflow_boundary(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('absolute UTC timestamp');

        $this->orchestrator($this->createStub(Repository::class))->checks(
            'automation/dependencies-100-1',
            str_repeat('a', 40),
            '2026-07-24T20:00:00+12:00',
        );
    }

    private function github(Queue $runner): GitHub
    {
        return new GitHub(
            $runner,
            'appwrite/docker-base',
            '2026-03-10',
        );
    }

    /**
     * @return Repository&MockObject
     */
    private function repository(): Repository
    {
        return $this->createMock(Repository::class);
    }

    private function orchestrator(Repository $repository): Orchestrator
    {
        return new Orchestrator(
            $repository,
            $this->createStub(Clock::class),
            $this->createStub(Sleeper::class),
        );
    }

    private function commandResult(
        int $code = 0,
        string $output = '{}',
    ): Result {
        return new Result($code, $output, 'failure');
    }

    /**
     * @return array<string, int|string|bool>
     */
    private function release(
        int $identifier,
        string $tag,
        string $target,
        bool $draft,
        bool $prerelease = false,
        string $body = '',
    ): array {
        return [
            'id' => $identifier,
            'tag_name' => $tag,
            'target_commitish' => $target,
            'draft' => $draft,
            'prerelease' => $prerelease,
            'body' => $body,
        ];
    }

    /**
     * @return array<string, array<string, string>|int|string|null>
     */
    private function pull(
        string $head,
        string $base,
        string $state = 'OPEN',
        ?string $commit = null,
    ): array {
        return [
            'baseRefName' => 'main',
            'baseRefOid' => $base,
            'headRefOid' => $head,
            'mergeCommit' => $commit === null ? null : ['oid' => $commit],
            'mergeable' => 'MERGEABLE',
            'number' => 75,
            'reviewDecision' => 'APPROVED',
            'state' => $state,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function pages(array $items): string
    {
        return json_encode([$items], JSON_THROW_ON_ERROR);
    }

    private function pullBody(string $head, string $base): string
    {
        return '<!-- dependency-automation:v1 -->'
            . "\n<!-- dependency-tested-head:{$head} -->"
            . "\n<!-- dependency-tested-base:{$base} -->";
    }

    private function draftBody(string $target, int $pull): string
    {
        return '<!-- dependency-automation:v1 -->'
            . "\n<!-- dependency-target:{$target} -->"
            . "\n<!-- dependency-pull:{$pull} -->"
            . "\n\nAutomated weekly dependency release.";
    }
}
