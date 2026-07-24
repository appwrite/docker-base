<?php

declare(strict_types=1);

namespace DockerBase\Automation;

use InvalidArgumentException;

final readonly class WorkflowOutput
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(
        private array $values,
    ) {
        foreach ($values as $name => $value) {
            if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $name) !== 1) {
                throw new InvalidArgumentException(
                    "Invalid workflow output name '{$name}'",
                );
            }
            if (str_contains($value, "\n") || str_contains($value, "\r")) {
                throw new InvalidArgumentException(
                    "Workflow output '{$name}' must fit on one line",
                );
            }
        }
    }

    public static function recovery(?Candidate $candidate): self
    {
        if ($candidate === null) {
            return new self(['pending' => 'false']);
        }

        return new self([
            'pending' => 'true',
            'tag' => $candidate->tag ?? '',
            'head' => $candidate->target,
            'pull' => (string) $candidate->pull,
            'draft' => $candidate->draft === null
                ? ''
                : (string) $candidate->draft,
        ]);
    }

    public static function preparation(Preparation $preparation): self
    {
        return new self([
            'tag' => $preparation->tag,
            'head' => $preparation->target,
            'pull' => (string) $preparation->pull,
            'draft' => (string) $preparation->draft,
        ]);
    }

    public static function merge(string $head): self
    {
        return new self(['head' => $head]);
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function render(): string
    {
        $lines = [];
        foreach ($this->values as $name => $value) {
            $lines[] = "{$name}={$value}";
        }

        return $lines === [] ? '' : implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
