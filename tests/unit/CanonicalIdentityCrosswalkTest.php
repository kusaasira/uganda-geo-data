<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Data\CanonicalIdentityCrosswalk;

final class CanonicalIdentityCrosswalkTest extends TestCase
{
    /** @test */
    public function reportsVerifiedUnresolvedAndUnmappedSourceUnits(): void
    {
        $snapshot = ['units' => [['id' => 'legacy:parish:1'], ['id' => 'legacy:parish:2'], ['id' => 'legacy:parish:3']]];
        $links = [
            ['legacy_id' => 'legacy:parish:1', 'canonical_id' => 'geo:parish:one',
                'status' => 'verified', 'evidence_url' => 'https://example.org/source',
                'evidence_date' => '2024-05-10'],
            ['legacy_id' => 'legacy:parish:2', 'canonical_id' => null, 'status' => 'unresolved'],
        ];
        $result = (new CanonicalIdentityCrosswalk())->validate($snapshot, $links);
        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
        $this->assertSame(['legacy:parish:1'], $result['verified_ids']);
        $this->assertSame(['legacy:parish:2'], $result['unresolved_ids']);
        $this->assertSame(['legacy:parish:3'], $result['unmapped_ids']);
    }

    /** @test */
    public function rejectsUndocumentedClaimsAndDuplicateCanonicalAssignments(): void
    {
        $snapshot = ['units' => [['id' => 'legacy:parish:1'], ['id' => 'legacy:parish:2']]];
        $links = [
            ['legacy_id' => 'legacy:parish:1', 'canonical_id' => 'geo:parish:one', 'status' => 'verified'],
            ['legacy_id' => 'legacy:parish:2', 'canonical_id' => 'geo:parish:one',
                'status' => 'verified', 'evidence_url' => 'https://example.org/source',
                'evidence_date' => '2024-05-10'],
        ];
        $result = (new CanonicalIdentityCrosswalk())->validate($snapshot, $links);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    /** @test */
    public function rejectsClaimsAboutAbsentLegacyRecords(): void
    {
        $result = (new CanonicalIdentityCrosswalk())->validate(
            ['units' => [['id' => 'legacy:parish:1']]],
            [['legacy_id' => 'legacy:parish:2', 'canonical_id' => null, 'status' => 'unresolved']]
        );
        $this->assertFalse($result['valid']);
    }
}
