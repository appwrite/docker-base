<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Version
{
    public function __construct(
        public string $major,
        public string $minor,
        public string $patch,
    ) {
    }

    public static function parse(string $spelling): ?self
    {
        $matched = preg_match(
            '/\Av?(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\z/',
            $spelling,
            $parts,
        );
        if ($matched !== 1) {
            return null;
        }

        return new self(
            $parts[1],
            $parts[2],
            $parts[3],
        );
    }

    public function compare(self $other): int
    {
        foreach ([
            [$this->major, $other->major],
            [$this->minor, $other->minor],
            [$this->patch, $other->patch],
        ] as [$current, $candidate]) {
            $comparison = strlen($current) <=> strlen($candidate);
            if ($comparison !== 0) {
                return $comparison;
            }

            $comparison = $current <=> $candidate;
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }
}
