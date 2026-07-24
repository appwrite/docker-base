<?php

declare(strict_types=1);

namespace DockerBase\Parity;

use DOMDocument;
use DOMElement;
use DOMXPath;
use JsonException;

final readonly class Verifier
{
    public function __construct(
        private string $manifest,
        private string $results,
    ) {
    }

    public function verify(): int
    {
        $contracts = $this->contracts();
        $executed = $this->executed();

        foreach ($contracts as $contract) {
            $states = $executed[$contract] ?? [];
            if ($states === []) {
                throw new Exception(
                    "Mapped parity test {$contract} was not executed",
                );
            }
            foreach (['error', 'failed', 'skipped'] as $state) {
                if (in_array($state, $states, true)) {
                    throw new Exception(
                        "Mapped parity test {$contract} {$state}",
                    );
                }
            }
        }

        return count($contracts);
    }

    /**
     * @return list<string>
     */
    private function contracts(): array
    {
        $content = file_get_contents($this->manifest);
        if ($content === false) {
            throw new Exception(
                "Unable to read parity manifest {$this->manifest}",
            );
        }

        try {
            $manifest = json_decode(
                $content,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new Exception(
                'Invalid parity manifest: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
        if (! is_array($manifest)) {
            throw new Exception('Parity manifest must be an object');
        }
        $baseline = $manifest['baseline'] ?? null;
        $items = $manifest['contracts'] ?? null;
        if (
            ! is_array($baseline)
            || ! is_int($baseline['total'] ?? null)
            || ! is_array($items)
        ) {
            throw new Exception('Parity manifest structure is invalid');
        }

        $contracts = [];
        foreach ($items as $item) {
            $contract = is_array($item) ? ($item['php'] ?? null) : null;
            if (
                ! is_string($contract)
                || preg_match(
                    '/\A[A-Za-z_][A-Za-z0-9_\\\\]*::'
                        . '[A-Za-z_][A-Za-z0-9_]*\z/D',
                    $contract,
                ) !== 1
            ) {
                throw new Exception(
                    'Parity manifest contains an invalid PHP contract',
                );
            }
            $contracts[] = $contract;
        }
        if ($baseline['total'] !== count($contracts)) {
            throw new Exception(
                'Parity manifest total does not match its contracts',
            );
        }
        if (count(array_unique($contracts)) !== count($contracts)) {
            throw new Exception(
                'Parity manifest contains duplicate PHP contracts',
            );
        }

        return $contracts;
    }

    /**
     * @return array<string, list<string>>
     */
    private function executed(): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $loaded = $document->load($this->results, LIBXML_NONET);
            if (! $loaded) {
                $errors = libxml_get_errors();
                $detail = trim(
                    $errors[0]->message ?? 'unknown XML error',
                );

                throw new Exception(
                    "Invalid PHPUnit results: {$detail}",
                );
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $nodes = (new DOMXPath($document))->query('//testcase');
        if ($nodes === false) {
            throw new Exception('Unable to query PHPUnit results');
        }

        $executed = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $class = $node->getAttribute('class');
            $name = $node->getAttribute('name');
            $dataset = strpos($name, ' with data set ');
            $method = $dataset === false
                ? $name
                : substr($name, 0, $dataset);
            if ($class === '' || $method === '') {
                continue;
            }

            $state = match (true) {
                $node->getElementsByTagName('error')->length > 0 => 'error',
                $node->getElementsByTagName('failure')->length > 0 => 'failed',
                $node->getElementsByTagName('skipped')->length > 0 => 'skipped',
                default => 'passed',
            };
            $executed["{$class}::{$method}"][] = $state;
        }

        return $executed;
    }
}
