<?php

declare(strict_types=1);

namespace DockerBase\Automation;

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
                'An orchestration operation is required',
            );
        }

        [$operation, $values] = [
            array_shift($arguments),
            $arguments,
        ];

        return match ([$operation, count($values)]) {
            ['recover', 0] => $this->recover(),
            ['merge', 3] => [
                'head' => $this->orchestrator->merge(
                    $this->integer($values[0]),
                    $values[1],
                    $values[2],
                ),
            ],
            ['prepare', 4] => $this->preparation(
                $this->orchestrator->prepare(
                    $values[0] === '' ? null : $values[0],
                    $values[1],
                    $this->integer($values[2]),
                    $values[3] === '' ? null : $this->integer($values[3]),
                ),
            ),
            ['wait', 2] => $this->wait($values[0], $values[1]),
            ['publish', 4] => $this->publish(
                $values[0],
                $values[1],
                $this->integer($values[2]),
                $this->integer($values[3]),
            ),
            default => throw new InvalidArgumentException(
                "Invalid '{$operation}' arguments",
            ),
        };
    }

    /**
     * @return array<string, string>
     */
    private function recover(): array
    {
        $candidate = $this->orchestrator->recover();
        if ($candidate === null) {
            return ['pending' => 'false'];
        }

        return [
            'pending' => 'true',
            'tag' => $candidate->tag ?? '',
            'head' => $candidate->target,
            'pull' => (string) $candidate->pull,
            'draft' => $candidate->draft === null
                ? ''
                : (string) $candidate->draft,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function preparation(Preparation $preparation): array
    {
        return [
            'tag' => $preparation->tag,
            'head' => $preparation->target,
            'pull' => (string) $preparation->pull,
            'draft' => (string) $preparation->draft,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function wait(string $tag, string $target): array
    {
        $this->orchestrator->wait($tag, $target);

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function publish(
        string $tag,
        string $target,
        int $pull,
        int $draft,
    ): array {
        $this->orchestrator->publish($tag, $target, $pull, $draft);

        return [];
    }

    private function integer(string $value): int
    {
        if (
            filter_var(
                $value,
                FILTER_VALIDATE_INT,
                FILTER_NULL_ON_FAILURE,
            ) === null
        ) {
            throw new InvalidArgumentException(
                "Invalid integer '{$value}'",
            );
        }

        return (int) $value;
    }
}
