<?php

declare(strict_types=1);

namespace Uganda\Release;

use InvalidArgumentException;

final class SoftwareBillOfMaterials
{
    /**
     * Build a deterministic CycloneDX 1.5 runtime inventory from the Composer
     * package definition and resolved production lock, never dev packages.
     *
     * @param array<string, mixed> $manifest
     * @param array<string, mixed>|null $lock
     * @return array<string, mixed>
     */
    public function build(array $manifest, ?array $lock, string $version): array
    {
        $name = $manifest['name'] ?? null;
        $license = $manifest['license'] ?? null;
        $requires = $manifest['require'] ?? null;
        if (!is_string($name) || !preg_match('/^[a-z0-9._-]+\/[a-z0-9._-]+$/', $name)
            || !is_string($license) || trim($license) === ''
            || !is_array($requires) || trim($version) === '') {
            throw new InvalidArgumentException('Invalid package metadata for software bill of materials');
        }
        $resolved = [];
        foreach ($lock['packages'] ?? [] as $package) {
            if (!is_array($package) || !is_string($package['name'] ?? null)
                || !is_string($package['version'] ?? null)) {
                throw new InvalidArgumentException('Invalid Composer runtime lock');
            }
            $resolved[$package['name']] = $package;
        }
        $components = [];
        foreach ($requires as $dependency => $constraint) {
            if (!is_string($dependency) || !is_string($constraint)) {
                throw new InvalidArgumentException('Invalid runtime dependency definition');
            }
            if ($dependency === 'php' || str_starts_with($dependency, 'ext-')) {
                $components[] = [
                    'type' => 'framework',
                    'name' => $dependency,
                    'version' => $constraint,
                    'description' => 'Composer platform constraint; not an installed runtime version',
                ];
                continue;
            }
            $package = $resolved[$dependency] ?? null;
            if ($package === null) {
                throw new InvalidArgumentException('Unresolved production dependency: ' . $dependency);
            }
            $component = ['type' => 'library', 'name' => $dependency, 'version' => $package['version']];
            if (is_array($package['license'] ?? null) && $package['license'] !== []) {
                $component['licenses'] = array_map(static function (string $id): array {
                    return ['license' => ['id' => $id]];
                }, $package['license']);
            }
            $components[] = $component;
        }
        usort($components, static function (array $left, array $right): int {
            return strcmp($left['name'], $right['name']);
        });

        return [
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.5',
            'version' => 1,
            'metadata' => ['component' => [
                'type' => 'library',
                'name' => $name,
                'version' => $version,
                'licenses' => [['license' => ['id' => $license]]],
            ]],
            'components' => $components,
        ];
    }
}
