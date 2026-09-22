<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

use InvalidArgumentException;

final readonly class Application
{
    public function __construct(
        private Orchestrator $orchestrator,
    ) {
    }

    /**
     * @param list<string> $arguments
     *
     * @return array<string, string>
     */
    public function execute(array $arguments): array
    {
        if ($arguments === []) {
            throw new InvalidArgumentException(
                'A downstream operation is required',
            );
        }

        [$operation, $values] = [array_shift($arguments), $arguments];

        return match ([$operation, count($values)]) {
            ['recover', 1] => $this->recover($values[0]),
            ['propose', 1] => $this->propose($values[0]),
            ['wait', 1] => $this->wait($this->integer($values[0])),
            ['release', 2] => $this->release(
                $this->integer($values[0]),
                $values[1],
            ),
            default => throw new InvalidArgumentException(
                "Unknown downstream operation '{$operation}'",
            ),
        };
    }

    /**
     * @return array<string, string>
     */
    private function recover(string $version): array
    {
        $release = $this->orchestrator->recover($version);
        if ($release === null) {
            return ['recovered' => 'false'];
        }

        return [
            'recovered' => 'true',
            'tag' => (string) $release,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function propose(string $version): array
    {
        $pull = $this->orchestrator->propose($version);
        if ($pull === null) {
            return ['changed' => 'false'];
        }

        return [
            'changed' => 'true',
            'pull' => (string) $pull->number,
            'head' => $pull->head,
            'base' => $pull->base,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function wait(int $pull): array
    {
        $this->orchestrator->wait($pull);

        return ['checks' => 'success'];
    }

    /**
     * @return array<string, string>
     */
    private function release(int $pull, string $head): array
    {
        $release = $this->orchestrator->release($pull, $head);

        return [
            'tag' => (string) $release,
            'application' => $release->application,
            'sub' => (string) $release->sub,
        ];
    }

    private function integer(string $value): int
    {
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new InvalidArgumentException(
                "Expected a positive integer, got '{$value}'",
            );
        }

        return (int) $value;
    }
}
