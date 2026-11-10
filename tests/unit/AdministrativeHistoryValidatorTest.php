<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Data\AdministrativeHistoryValidator;

final class AdministrativeHistoryValidatorTest extends TestCase
{
    /** @test */
    public function recordsDocumentedRenamesAndSplits(): void
    {
        $events = [
            ['id' => 'e1', 'kind' => 'rename', 'from_ids' => ['unit:1'], 'to_ids' => ['unit:1'],
                'effective_date' => '2024-07-01', 'evidence_url' => 'https://example.org/notice'],
            ['id' => 'e2', 'kind' => 'split', 'from_ids' => ['unit:1'], 'to_ids' => ['unit:2', 'unit:3'],
                'effective_date' => '2025-07-01', 'evidence_url' => 'https://example.org/notice'],
        ];
        $report = (new AdministrativeHistoryValidator())->validate($events);
        $this->assertTrue($report['valid'], implode('; ', $report['errors']));
        $this->assertSame(2, $report['count']);
    }

    /** @test */
    public function rejectsBadCardinalityAndUnverifiedDates(): void
    {
        $events = [['id' => 'e1', 'kind' => 'split', 'from_ids' => ['unit:1'], 'to_ids' => ['unit:2'],
            'effective_date' => '2025-02-30', 'evidence_url' => 'http://example.org/notice']];
        $this->assertFalse((new AdministrativeHistoryValidator())->validate($events)['valid']);
    }
    /** @test */
    public function rejectsRenamesThatSilentlyChangeAnAdministrativeIdentity(): void
    {
        $events = [['id' => 'event-1', 'kind' => 'rename', 'from_ids' => ['unit:1'],
            'to_ids' => ['unit:2'], 'effective_date' => '2025-01-01',
            'evidence_url' => 'https://example.org/official-notice']];
        $report = (new AdministrativeHistoryValidator())->validate($events);
        $this->assertFalse($report['valid']);
        $this->assertNotEmpty($report['errors']);
    }
    /** @test */
    public function rejectsEvidenceReferencesWithoutAValidHost(): void
    {
        $report = (new AdministrativeHistoryValidator())->validate([[
            'id' => 'event-1', 'kind' => 'rename', 'from_ids' => ['unit:1'],
            'to_ids' => ['unit:1'], 'effective_date' => '2024-05-01',
            'evidence_url' => 'https://',
        ]]);
        $this->assertFalse($report['valid']);
    }
}
