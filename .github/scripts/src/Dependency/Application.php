<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

use DockerBase\Command\Runner;

final readonly class Application
{
    public function __construct(
        private Catalog $catalog,
        private Dockerfile $dockerfile,
        private Resolver $resolver,
        private Selector $selector,
    ) {
    }

    public static function create(Runner $runner, Fetcher $fetcher): self
    {
        $catalog = Catalog::create();

        return new self(
            $catalog,
            new Dockerfile(),
            new Resolver($runner, $fetcher),
            new Selector(),
        );
    }

    public function plan(string $content): Plan
    {
        $dependencies = $this->catalog->dependencies();
        $pins = $this->dockerfile->pins($content, $this->catalog);
        $expected = 1 + (count($dependencies) * 2);
        if (count($pins) !== $expected) {
            throw new Exception(
                'Every dependency must pin a version and a reference',
            );
        }

        $digest = $this->resolver->digest();
        $selected = [$digest];
        $changes = [new Change($pins[0]->name, $pins[0]->current, $digest)];

        foreach ($dependencies as $index => $dependency) {
            $version = $pins[($index * 2) + 1];
            $reference = $pins[($index * 2) + 2];
            $current = new Release($version->current, $reference->current);
            $releases = $this->resolver->releases($dependency);
            $release = $this->selector->select($current, $releases);
            $resolved = $this->resolver->reference(
                $dependency,
                $release->version,
                $releases,
            );

            $selected[] = $release->version;
            $selected[] = $resolved;
            $changes[] = new Change(
                $dependency->name,
                $current->version,
                $release->version,
                $reference->current,
                $resolved,
            );
        }

        return new Plan(
            $this->dockerfile->replace($content, $pins, $selected),
            $changes,
        );
    }
}
