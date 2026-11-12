<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\ReleaseNotes;

final class PatchReleaseNotesTest extends TestCase
{
    /** @test */
    public function aPatchReleaseDoesNotInheritTheMajorUpgradeWarning(): void
    {
        $manifest = ['base_version' => 'v2.0.1', 'sections' => [
            'Fixed' => ['Reduce redundant child-index allocation while preserving lookup results.'],
        ]];
        $notes = (new ReleaseNotes())->render($manifest, 'v2.0.1');

        self::assertStringContainsString('backward-compatible maintenance release', $notes);
        self::assertStringNotContainsString('requires migration from the existing v1.0.0', $notes);
        self::assertStringContainsString('## Fixed', $notes);
    }

    /** @test */
    public function aPatchReleaseCandidateIsSupported(): void
    {
        $manifest = ['base_version' => 'v2.0.1', 'sections' => [
            'Fixed' => ['Validate patch release readiness.'],
        ]];

        self::assertStringContainsString(
            '# Uganda Geo v2.0.1-rc.1',
            (new ReleaseNotes())->render($manifest, 'v2.0.1-rc.1')
        );
    }

    /** @test */
    public function aMajorManifestCannotBeReusedForAPatchTag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReleaseNotes())->render([
            'base_version' => 'v2.0.0',
            'sections' => ['Fixed' => ['This belongs to a different tag.']],
        ], 'v2.0.1');
    }

    /** @test */
    public function unsupportedFutureReleaseTagsRemainRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReleaseNotes())->render([
            'base_version' => 'v2.0.1',
            'sections' => ['Fixed' => ['Must be versioned independently.']],
        ], 'v2.1.0');
    }
}
