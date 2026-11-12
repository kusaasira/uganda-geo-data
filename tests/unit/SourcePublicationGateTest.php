<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\SourcePublicationGate;

final class SourcePublicationGateTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itAcceptsDocumentedRedistributionGrants(): void
    {
        (new SourcePublicationGate())->assertMayPublish(['sources' => [
            ['id' => 'approved', 'redistribution_status' => 'permitted',
                'license_url' => 'https://example.org/license', 'attribution' => 'Example GIS'],
        ]], ['approved']);
        self::assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsPubliclyAccessibleButUnverifiedSources(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SourcePublicationGate())->assertMayPublish(['sources' => [
            ['id' => 'public', 'redistribution_status' => 'unverified'],
        ]], ['public']);
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsConflictingSourceRegistryIdentities(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SourcePublicationGate())->assertMayPublish(['sources' => [
            ['id' => 'same', 'redistribution_status' => 'restricted'],
            ['id' => 'same', 'redistribution_status' => 'permitted',
                'license_url' => 'https://example.org/license', 'attribution' => 'Example'],
        ]], ['same']);
    }
}
