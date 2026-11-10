<?php

declare(strict_types=1);

namespace Uganda\Release;

use InvalidArgumentException;

final class ArtifactManifest
{
    /**
     * @param array<int, string> $files
     * @return array<string, mixed>
     */
    public function build(string $root, array $files): array
    {
        $entries = [];
        foreach ($files as $relative) {
            $path = $this->resolve($root, $relative);
            if (isset($entries[$relative])) {
                throw new InvalidArgumentException('Duplicate artifact path ' . $relative);
            }
            $hash = hash_file('sha256', $path);
            $bytes = filesize($path);
            if ($hash === false || $bytes === false) {
                throw new InvalidArgumentException('Unable to hash artifact ' . $relative);
            }
            $entries[$relative] = ['path' => $relative, 'sha256' => $hash, 'bytes' => $bytes];
        }
        ksort($entries, SORT_STRING);

        return ['schema_version' => 1, 'hash_algorithm' => 'sha256', 'artifacts' => array_values($entries)];
    }

    /** @param array<string, mixed> $manifest */
    public function verify(string $root, array $manifest): void
    {
        if (($manifest['schema_version'] ?? null) !== 1 || ($manifest['hash_algorithm'] ?? null) !== 'sha256'
            || !isset($manifest['artifacts']) || !is_array($manifest['artifacts'])) {
            throw new InvalidArgumentException('Unsupported or incomplete release manifest');
        }
        $paths = [];
        foreach ($manifest['artifacts'] as $entry) {
            if (!is_array($entry) || !isset($entry['path'], $entry['sha256'], $entry['bytes'])
                || !is_string($entry['path']) || !is_string($entry['sha256']) || !is_int($entry['bytes'])
                || !preg_match('/^[a-f0-9]{64}$/', $entry['sha256']) || isset($paths[$entry['path']])) {
                throw new InvalidArgumentException('Invalid or duplicate release artifact entry');
            }
            $paths[$entry['path']] = true;
            $path = $this->resolve($root, $entry['path']);
            if (!hash_equals($entry['sha256'], (string) hash_file('sha256', $path))
                || filesize($path) !== $entry['bytes']) {
                throw new InvalidArgumentException('Release artifact mismatch: ' . $entry['path']);
            }
        }
    }

    private function resolve(string $root, string $relative): string
    {
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '\\')
            || !preg_match('/^[A-Za-z0-9._\/-]+$/D', $relative)
            || in_array('..', explode('/', $relative), true)) {
            throw new InvalidArgumentException('Unsafe artifact path');
        }

        $directory = realpath($root);
        $path = $directory === false ? false : realpath($directory . '/' . $relative);
        if ($directory === false || $path === false || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR)
            || !is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('Artifact outside release root or unreadable: ' . $relative);
        }

        return $path;
    }
}
