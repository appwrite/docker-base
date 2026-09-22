<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Downstream;

use DockerBase\Downstream\Exception;
use DockerBase\Downstream\Pull;
use DockerBase\Downstream\Repository;
use DockerBase\Downstream\Status;
use DockerBase\Downstream\Tag;
use Override;

final class Fake implements Repository
{
    /** @var list<string> */
    public array $calls = [];

    public ?string $tagged = null;

    /** The commit the tag was put on. */
    public ?string $taggedTarget = null;

    /** True once a merge bypassed branch protection. */
    public bool $bypassed = false;

    /** True once a merge went through branch protection. */
    public bool $mergedUnderProtection = false;

    /**
     * @param list<Tag> $tags
     * @param list<string> $requiredChecks
     * @param list<list<array{name: string, status: string, conclusion: string}>> $rounds
     * @param list<string> $states
     */
    public function __construct(
        private readonly string $dockerfile,
        private readonly string $constants = "<?php\nconst APP_VERSION_STABLE = '1.9.6';\n",
        private readonly array $tags = [],
        private array $rounds = [],
        private readonly string $head = 'a0000000000000000000000000000000000000aa',
        private readonly string $mergeCommit = 'b0000000000000000000000000000000000000bb',
        private readonly ?string $merged = null,
        private readonly bool $contained = true,
        private readonly array $requiredChecks = ['Tests / Unit'],
        private array $states = [],
        private readonly ?string $protectionRefusal = null,
    ) {
    }

    #[Override]
    public function file(string $path, string $ref): string
    {
        $this->calls[] = "file:{$path}";

        return str_ends_with($path, 'constants.php')
            ? $this->constants
            : $this->dockerfile;
    }

    #[Override]
    public function head(string $branch): string
    {
        $this->calls[] = "head:{$branch}";

        return $this->head;
    }

    /**
     * @return list<Tag>
     */
    #[Override]
    public function tags(string $prefix): array
    {
        $this->calls[] = "tags:{$prefix}";

        return $this->tags === []
            ? [new Tag('cl-1.9.6-1', 'c0000000000000000000000000000000000000cc')]
            : $this->tags;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function required(string $branch): array
    {
        $this->calls[] = "required:{$branch}";

        return $this->requiredChecks;
    }

    #[Override]
    public function contains(string $branch, string $commit): bool
    {
        $this->calls[] = "contains:{$branch}";

        return $this->contained;
    }

    #[Override]
    public function mergeCommit(string $branch): ?string
    {
        $this->calls[] = "merged:{$branch}";

        return $this->merged;
    }

    #[Override]
    public function commit(
        string $branch,
        string $base,
        string $path,
        string $content,
        string $message,
    ): string {
        $this->calls[] = "commit:{$branch}";

        return $this->mergeCommit;
    }

    #[Override]
    public function open(
        string $branch,
        string $base,
        string $title,
        string $body,
    ): Pull {
        $this->calls[] = "open:{$branch}->{$base}";

        return new Pull(93, $this->head, $base);
    }

    #[Override]
    public function status(int $pull): Status
    {
        $this->calls[] = "status:{$pull}";
        if ($this->rounds === []) {
            throw new Exception('No further status rounds');
        }

        return new Status(
            array_shift($this->rounds),
            array_shift($this->states) ?? 'CLEAN',
        );
    }

    #[Override]
    public function merge(int $pull, string $head, bool $bypass): string
    {
        $this->calls[] = "merge:{$pull}@{$head}";

        if (! is_null($this->protectionRefusal) && ! $bypass) {
            throw new Exception(
                "GitHub refused to merge pull request #{$pull} at {$head}: "
                . $this->protectionRefusal,
            );
        }

        if ($bypass) {
            $this->bypassed = true;
        } else {
            $this->mergedUnderProtection = true;
        }

        return $this->mergeCommit;
    }

    #[Override]
    public function tag(string $name, string $target): void
    {
        $this->calls[] = "tag:{$name}@{$target}";
        $this->tagged = $name;
        $this->taggedTarget = $target;
    }
}
