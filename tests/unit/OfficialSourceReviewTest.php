<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Data\OfficialSourceReview;

final class OfficialSourceReviewTest extends TestCase
{
    /** @test */
    public function keepsStatisticalAndLegalReferenceDatesDistinct(): void
    {
        $manifest = [
            'publications' => [
                ['id' => 'bec', 'publisher' => 'UBOS', 'title' => 'BEC',
                    'url' => 'https://www.ubos.org/2026/09/21/baseline-education-census-bec-report-2025/',
                    'scope' => 'Education by parish', 'status' => 'contextual_aggregate',
                    'reference_year' => 2025, 'publication_date' => '2026-09-21',
                    'legal_effective_date' => null, 'unit_level_identity_verified' => false],
            ],
        ];
        $result = (new OfficialSourceReview())->review($manifest);
        $this->assertSame(1, $result['source_count']);
        $this->assertSame(0, $result['identity_ready_sources']);
        $this->assertSame(2025, $result['publications'][0]['reference_year']);
        $this->assertSame('2026-09-21', $result['publications'][0]['publication_date']);
        $this->assertTrue($result['publications'][0]['requires_source_crosswalk']);
        $this->assertNull($result['publications'][0]['legal_effective_date']);
    }

    /** @test */
    public function rejectsImpossiblePublicationDates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new OfficialSourceReview())->review(['publications' => [[
            'id' => 'bad', 'publisher' => 'UBOS', 'title' => 'Bad', 'url' => 'https://www.ubos.org/',
            'scope' => 'Example', 'status' => 'undated_profile', 'reference_year' => 2025,
            'publication_date' => '2025-02-30', 'unit_level_identity_verified' => false,
        ]]]);
    }
}
