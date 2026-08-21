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
        $pins = $this->dockerfile->pins($content, $this->catalog);
        $selected = [$this->resolver->digest()];

        foreach ($this->catalog->dependencies() as $index => $dependency) {
            $releases = $this->resolver->releases($dependency);
            $selected[] = $this->selector->select(
                $pins[$index + 1]->current,
                $releases,
            );
        }

        $changes = [];
        foreach ($pins as $index => $pin) {
            $changes[] = new Change(
                $pin->name,
                $pin->current,
                $selected[$index],
            );
        }

        return new Plan(
            $this->dockerfile->replace($content, $pins, $selected),
            $changes,
        );
    }
}
