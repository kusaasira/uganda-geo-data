<?php

declare(strict_types=1);

namespace unit;

use JsonException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\ReleaseNotes;

final class V2ReleaseManifestTest extends TestCase
{
    /**
     * @test
     * @throws JsonException
     */
    public function itKeepsMajorReleaseChangesAndDataLimitationsExplicit(): void
    {
        $path = dirname(__DIR__, 2) . '/resources/releases/v2-notes.json';
        $source = file_get_contents($path);
        self::assertIsString($source);
        $manifest = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);

        foreach (['Added', 'Changed', 'Fixed', 'Data', 'Geometry', 'Security', 'Documentation', 'Upgrade'] as $category) {
            self::assertNotEmpty($manifest['sections'][$category] ?? [], $category);
        }

        $notes = (new ReleaseNotes())->render($manifest, 'v2.0.0-rc.1');

        self::assertStringContainsString('historical', strtolower($notes));
        self::assertStringContainsString('not verified', strtolower($notes));
        self::assertStringContainsString('redistribution', strtolower($notes));
        self::assertStringContainsString('v1-to-v2', strtolower($notes));
        self::assertStringContainsString('PHP 8.2', $notes);
    }
}
