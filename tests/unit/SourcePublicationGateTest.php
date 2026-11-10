<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\SourcePublicationGate;

final class SourcePublicationGateTest extends TestCase
{
    /** @test */
    public function itAcceptsDocumentedRedistributionGrants(): void
    {
        (new SourcePublicationGate())->assertMayPublish(['sources' => [
            ['id' => 'approved', 'redistribution_status' => 'permitted',
                'license_url' => 'https://example.org/license', 'attribution' => 'Example GIS'],
        ]], ['approved']);
        self::assertTrue(true);
    }

    /** @test */
    public function itRejectsPubliclyAccessibleButUnverifiedSources(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SourcePublicationGate())->assertMayPublish(['sources' => [
            ['id' => 'public', 'redistribution_status' => 'unverified'],
        ]], ['public']);
    }
}
