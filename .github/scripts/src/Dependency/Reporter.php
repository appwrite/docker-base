<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Reporter
{
    public function render(Plan $plan): string
    {
        $changed = 0;
        $rows = [
            '## Dependency update report',
            '',
            '| Dependency | Current | Selected | Result |',
            '| --- | --- | --- | --- |',
        ];

        foreach ($plan->changes as $change) {
            if ($change->changed()) {
                ++$changed;
            }

            $result = $change->changed() ? 'Updated' : 'Current';
            $rows[] = "| {$change->name} | `{$change->current}` "
                . "| `{$change->latest}` | {$result} |";
        }

        $rows[] = '';
        $rows[] = "**Updates:** {$changed}";
        $rows[] = '';
        $rows[] = $changed > 0
            ? 'Dockerfile pins were updated.'
            : 'No dependency updates were found.';

        return implode("\n", $rows);
    }
}
