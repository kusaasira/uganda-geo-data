<?php

declare(strict_types=1);

namespace Uganda\Release;

use InvalidArgumentException;

final class ReleaseNotes
{
    private const CATEGORIES = ['Added', 'Changed', 'Fixed', 'Data', 'Geometry', 'Security', 'Documentation', 'Upgrade'];

    /**
     * @param array<string, mixed> $manifest
     */
    public function render(array $manifest, string $tag): string
    {
        if (!preg_match('/^v2\.0\.0(?:-(?:alpha|beta|rc)\.[1-9][0-9]*)?$/D', $tag)) {
            throw new InvalidArgumentException('Expected a supported immutable Uganda Geo v2 release tag');
        }
        $sections = $manifest['sections'] ?? null;
        if (($manifest['base_version'] ?? null) !== 'v2.0.0'
            || !is_array($sections) || $sections === []) {
            throw new InvalidArgumentException('Release-note source requires base_version and sections');
        }
        $out = ['# Uganda Geo ' . $tag, '', '## Release summary', '',
            'This major release changes the PHP public API and requires migration from the existing v1.0.0 package.', ''];
        $seen = [];
        foreach (self::CATEGORIES as $category) {
            if (!array_key_exists($category, $sections)) {
                continue;
            }
            $items = $sections[$category];
            if (!is_array($items) || $items === []) {
                throw new InvalidArgumentException('Invalid release-note category: ' . $category);
            }
            $out[] = '## ' . $category;
            $out[] = '';
            foreach ($items as $item) {
                if (!is_string($item) || trim($item) === '' || str_contains($item, "\n")
                    || isset($seen[$category . ':' . $item])) {
                    throw new InvalidArgumentException('Invalid or duplicate release-note entry');
                }
                $seen[$category . ':' . $item] = true;
                $out[] = '- ' . $item;
            }
            $out[] = '';
        }
        foreach (array_keys($sections) as $category) {
            if (!in_array($category, self::CATEGORIES, true)) {
                throw new InvalidArgumentException('Unsupported release-note category: ' . $category);
            }
        }
        if ($seen === []) {
            throw new InvalidArgumentException('Release notes must describe actual changes');
        }
        $out[] = '## Release qualification';
        $out[] = '';
        $out[] = 'Release notes describe implemented features, not verification of national administrative accuracy, geometry coverage or redistribution permissions.';
        $out[] = 'Publish only after independent release gates and the exact-tag Packagist installation pass.';
        $out[] = '';
        return implode("\n", $out);
    }
}
