<?php

declare(strict_types=1);

namespace DockerBase\Automation;

interface Repository
{
    /**
     * @return list<Tag>
     */
    public function tags(): array;

    public function releaseByTag(string $tag): ?Recovery;

    /**
     * @param list<Tag> $tags
     *
     * @return list<Recovery>
     */
    public function releases(array $tags): array;

    /**
     * @return list<Merge>
     */
    public function mergedPullRequests(): array;

    public function pullRequest(int $number): PullRequest;

    public function squashMerge(
        int $number,
        string $head,
        string $base,
    ): MergeResult;

    /**
     * @return list<string>
     */
    public function commitParents(string $commit): array;

    public function tag(string $name): ?Tag;

    public function createTag(string $name, string $target): Tag;

    public function draft(int $identifier): Recovery;

    public function createDraft(
        string $tag,
        string $target,
        int $pull,
        string $body,
    ): Recovery;

    public function publish(Preparation $preparation): Recovery;

    public function redraft(Preparation $preparation): Recovery;

    /**
     * @return list<Run>
     */
    public function runs(
        string $workflow,
        string $event,
        string $head,
        string $branch,
    ): array;
}
