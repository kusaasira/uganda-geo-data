<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\Release\ReleaseNotes;
use Uganda\Release\SoftwareBillOfMaterials;

final class PatchReleaseEvidenceAlignmentTest extends TestCase
{
    #[Test]
    public function generated_patch_notes_cover_delivered_core_features(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode(
            (string) file_get_contents($root . '/resources/releases/v2.0.1-notes.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertSame('v2.0.1', $manifest['base_version']);
        $notes = (new ReleaseNotes())->render($manifest, 'v2.0.1-rc.1');

        foreach ([
            'Unicode', 'fuzzy', 'grapheme', 'bounded', 'memory', 'property',
            'source', 'ext-intl', 'ctype', 'Laravel', 'v2.0.1-rc.1',
        ] as $requirement) {
            self::assertStringContainsString(
                strtolower($requirement),
                strtolower($notes),
                'Patch release notes must describe completed v2.0.1 scope'
            );
        }
        self::assertStringNotContainsString(
            'requires migration from the existing v1.0.0',
            $notes
        );
    }

    #[Test]
    public function runtime_sbom_version_matches_the_patch_manifest(): void
    {
        $root = dirname(__DIR__, 2);
        $notes = json_decode(
            (string) file_get_contents($root . '/resources/releases/v2.0.1-notes.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $composer = json_decode(
            (string) file_get_contents($root . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $version = substr($notes['base_version'], 1) . '-dev';
        $report = (new SoftwareBillOfMaterials())->build($composer, null, $version);

        self::assertSame('2.0.1-dev', $version);
        self::assertSame($version, $report['metadata']['component']['version']);
        self::assertSame($composer['name'], $report['metadata']['component']['name']);
        self::assertSame(['ext-json', 'php'], array_column($report['components'], 'name'));
        self::assertArrayNotHasKey('ext-intl', $composer['require']);
        self::assertArrayNotHasKey('ext-ctype', $composer['require']);
    }

    #[Test]
    public function ci_manifest_version_is_validated_instead_of_hardcoded(): void
    {
        $workflow = (string) file_get_contents(
            dirname(__DIR__, 2) . '/.github/workflows/release-security.yml'
        );
        self::assertStringContainsString('resources/releases/v2.0.1-notes.json', $workflow);
        self::assertStringContainsString('SBOM_VERSION="$version"', $workflow);
        self::assertStringContainsString('getenv("SBOM_VERSION")', $workflow);
        self::assertStringNotContainsString(
            'create-sbom.php 2.0.0-dev',
            $workflow
        );
    }
}
