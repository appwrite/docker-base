<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

use DockerBase\Command\Exception as CommandException;
use DockerBase\Command\Runner;
use DockerBase\Dependency\Source\Git;
use DockerBase\Dependency\Source\PECL;
use DOMDocument;
use DOMElement;

final readonly class Resolver
{
    private const string DIGEST_PATTERN = 'sha256:[0-9a-f]{64}';

    public function __construct(
        private Runner $runner,
        private Fetcher $fetcher,
    ) {
    }

    public function digest(): string
    {
        $command = [
            'docker',
            'buildx',
            'imagetools',
            'inspect',
            Catalog::BASE,
        ];

        try {
            $output = $this->runner->run($command)->output;
        } catch (CommandException $exception) {
            throw new Exception($exception->getMessage(), previous: $exception);
        }

        $count = preg_match_all(
            '/^Digest:[ \t]*(' . self::DIGEST_PATTERN . ')[ \t]*$/m',
            $output,
            $matches,
        );
        if (
            $count !== 1
            || preg_match(
                '/\A' . self::DIGEST_PATTERN . '\z/',
                $matches[1][0],
            ) !== 1
        ) {
            throw new Exception(
                'Expected one lowercase sha256 digest for ' . Catalog::BASE,
            );
        }

        return $matches[1][0];
    }

    /**
     * @return list<string>
     */
    public function releases(Dependency $dependency): array
    {
        if ($dependency->source instanceof Git) {
            $command = [
                'git',
                'ls-remote',
                '--tags',
                '--refs',
                $dependency->source->url(),
            ];

            try {
                $output = $this->runner->run($command)->output;
            } catch (CommandException $exception) {
                throw new Exception($exception->getMessage(), previous: $exception);
            }

            $releases = $this->git($output);
        } elseif ($dependency->source instanceof PECL) {
            $releases = $this->pecl(
                $this->fetcher->fetch($dependency->source->url()),
            );
        } else {
            throw new Exception(
                "Unsupported source for {$dependency->name}",
            );
        }

        if ($releases === []) {
            throw new Exception(
                "No exact stable releases found for {$dependency->name}",
            );
        }

        return $releases;
    }

    /**
     * @return list<string>
     */
    public function git(string $output): array
    {
        $releases = [];
        $lines = preg_split('/\R/', $output);
        if ($lines === false) {
            throw new Exception('Unable to inspect git release tags');
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = preg_split('/\s+/', $line);
            if (
                $fields === false
                || count($fields) !== 2
                || ! str_starts_with($fields[1], 'refs/tags/')
            ) {
                continue;
            }

            $tag = substr($fields[1], strlen('refs/tags/'));
            if (Version::parse($tag) !== null) {
                $releases[] = $tag;
            }
        }

        return $releases;
    }

    /**
     * @return list<string>
     */
    public function pecl(string $document): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $xml = new DOMDocument();
            if (! $xml->loadXML($document, LIBXML_NONET)) {
                $errors = libxml_get_errors();
                $detail = trim($errors[0]->message ?? 'unknown error');

                throw new Exception(
                    "Invalid PECL release XML: {$detail}",
                );
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $releases = [];
        $root = $xml->documentElement;
        if ($root === null) {
            return $releases;
        }

        foreach ($root->childNodes as $release) {
            if (! $release instanceof DOMElement || $release->localName !== 'r') {
                continue;
            }

            $fields = [];
            foreach ($release->childNodes as $field) {
                if ($field instanceof DOMElement) {
                    $fields[$field->localName] = trim($field->textContent);
                }
            }

            $spelling = $fields['v'] ?? '';
            if (
                strtolower($fields['s'] ?? '') === 'stable'
                && Version::parse($spelling) !== null
            ) {
                $releases[] = $spelling;
            }
        }

        return $releases;
    }
}
