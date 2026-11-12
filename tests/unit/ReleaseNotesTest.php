<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\ReleaseNotes;

final class ReleaseNotesTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itProducesDeterministicOrderedMajorReleaseNotes(): void
    {
        $manifest = ['base_version' => 'v2.0.0', 'sections' => [
            'Upgrade' => ['Follow the v1-to-v2 migration guide.'],
            'Fixed' => ['Preserve same-name units by source identity.'],
            'Added' => ['Parent-scoped lookup.'],
        ]];
        $notes = (new ReleaseNotes())->render($manifest, 'v2.0.0-rc.1');
        self::assertSame($notes, (new ReleaseNotes())->render($manifest, 'v2.0.0-rc.1'));
        self::assertLessThan(strpos($notes, '## Fixed'), strpos($notes, '## Added'));
        self::assertStringContainsString('## Upgrade', $notes);
        self::assertStringContainsString('Before stable publication', $notes);
        self::assertStringContainsString('After publishing the stable tag', $notes);
        self::assertStringNotContainsString('exact-tag Packagist installation pass', $notes);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsUnsupportedReleaseVersions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReleaseNotes())->render(['base_version' => 'v2.0.0', 'sections' => ['Added' => ['X']]], 'v1.0.0');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsUndocumentedChangeCategories(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReleaseNotes())->render([
            'base_version' => 'v2.0.0', 'sections' => ['Unreviewed' => ['Do not publish']],
        ], 'v2.0.0');
    }
}
