<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Dockerfile
{
    private const string DIGEST_PATTERN = 'sha256:[0-9a-f]{64}';

    /**
     * @return list<Pin>
     */
    public function pins(string $content, Catalog $catalog): array
    {
        $expected = [];
        foreach ($catalog->dependencies() as $dependency) {
            $expected[$dependency->variable] = true;
        }

        $declared = [];
        $lines = $this->matches(
            '/^[ \t]*(?:(?:ARG|ENV)[ \t]+[^\r\n]*|PHP_[A-Za-z0-9_]+_VERSION[^\r\n]*)$/m',
            $content,
        );
        foreach ($lines as $line) {
            $variables = $this->matches(
                '/(?<![$A-Za-z0-9_])(PHP_[A-Za-z0-9_]+_VERSION)(?=[ \t]*(?:=|$))/',
                $line[0][0],
            );
            foreach ($variables as $variable) {
                $declared[$variable[1][0]] = true;
            }
        }

        $unknown = array_keys(array_diff_key($declared, $expected));
        sort($unknown, SORT_STRING);
        if ($unknown !== []) {
            $plural = count($unknown) === 1 ? '' : 's';

            throw new Exception(
                "Unknown PHP extension version declaration{$plural}: "
                . implode(', ', $unknown),
            );
        }

        $image = $this->single(
            '/^[ \t]*ARG[ \t]+BASE_IMAGE="([^"\r\n]+)"[ \t]*$/m',
            $content,
            'ARG BASE_IMAGE',
        );
        $value = $image[1][0];
        $base = preg_quote(Catalog::BASE, '/');
        $matched = preg_match(
            "/\\A{$base}@(" . self::DIGEST_PATTERN . ')\\z/',
            $value,
            $digest,
            PREG_OFFSET_CAPTURE,
        );
        if ($matched !== 1) {
            throw new Exception(
                'ARG BASE_IMAGE must pin ' . Catalog::BASE
                . ' to a lowercase sha256 digest',
            );
        }

        $start = $image[1][1] + $digest[1][1];
        $pins = [
            new Pin(
                Catalog::BASE,
                $digest[1][0],
                $start,
                $start + strlen($digest[1][0]),
            ),
        ];

        foreach ($catalog->dependencies() as $dependency) {
            $variable = preg_quote($dependency->variable, '/');
            $match = $this->single(
                "/^[ \t]*(?:ENV[ \t]+)?{$variable}=\"([^\"\\r\\n]+)\"[ \t]*(?:\\\\)?[ \t]*$/m",
                $content,
                $dependency->variable,
            );
            $current = $match[1][0];
            if (Version::parse($current) === null) {
                throw new Exception(
                    "{$dependency->variable} must be an exact stable "
                    . 'v?MAJOR.MINOR.PATCH version',
                );
            }

            $pins[] = new Pin(
                $dependency->name,
                $current,
                $match[1][1],
                $match[1][1] + strlen($current),
            );
        }

        return $pins;
    }

    /**
     * @param list<Pin> $pins
     * @param list<string> $selected
     */
    public function replace(
        string $content,
        array $pins,
        array $selected,
    ): string {
        if (count($pins) !== count($selected)) {
            throw new Exception('Every dependency pin must have a selected value');
        }

        for ($index = count($pins) - 1; $index >= 0; --$index) {
            $pin = $pins[$index];
            $content = substr($content, 0, $pin->start)
                . $selected[$index]
                . substr($content, $pin->end);
        }

        return $content;
    }

    /**
     * @return list<array<int|string, array{string, int}>>
     */
    private function matches(string $pattern, string $content): array
    {
        $count = preg_match_all(
            $pattern,
            $content,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );
        if ($count === false) {
            throw new Exception('Unable to inspect Dockerfile declarations');
        }

        return $matches;
    }

    /**
     * @return array<int|string, array{string, int}>
     */
    private function single(
        string $pattern,
        string $content,
        string $declaration,
    ): array {
        $matches = $this->matches($pattern, $content);
        if (count($matches) !== 1) {
            throw new Exception(
                "Expected exactly one {$declaration} declaration, found "
                . count($matches),
            );
        }

        return $matches[0];
    }
}
