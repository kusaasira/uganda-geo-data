<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class DataCorrectionContractTest extends TestCase
{
    /** @test */
    public function correctionIssueFormRequiresIdentityParentAndSourceEvidence(): void
    {
        $root = dirname(__DIR__, 2);
        $form = Yaml::parseFile($root . '/.github/ISSUE_TEMPLATE/data-correction.yml');
        $this->assertSame('Administrative data correction', $form['name']);

        $required = [
            'canonical-unit-id',
            'administrative-level',
            'current-name',
            'parent-unit-id',
            'parent-name',
            'existing-value',
            'proposed-correction',
            'evidence-url',
            'evidence-reference-date',
            'evidence-detail',
        ];
        $fields = [];
        foreach ($form['body'] as $entry) {
            if (isset($entry['id'])) {
                $fields[$entry['id']] = $entry;
            }
        }
        foreach ($required as $id) {
            $this->assertArrayHasKey($id, $fields);
            $this->assertTrue($fields[$id]['validations']['required']);
        }
    }

    /** @test */
    public function correctionSchemaPreservesReviewStatusAndDatedEvidence(): void
    {
        $schema = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/resources/schemas/data-correction.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertContains('evidence_url', $schema['required']);
        $this->assertContains('parent_unit_id', $schema['required']);
        $this->assertContains('approved', $schema['properties']['status']['enum']);
        $this->assertArrayHasKey('effective_date', $schema['properties']);
    }
}
