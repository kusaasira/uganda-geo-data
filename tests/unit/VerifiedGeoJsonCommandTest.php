<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class VerifiedGeoJsonCommandTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itExportsReviewedCoordinatesAndNeverOverwritesAnExistingFile(): void
    {
        $directory = sys_get_temp_dir() . '/uganda-geojson-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $inputs = [
            'source.geojson' => [
                'type' => 'FeatureCollection',
                'features' => [[
                    'type' => 'Feature',
                    'properties' => ['OBJECTID' => 12],
                    'geometry' => ['type' => 'Polygon', 'coordinates' => [
                        [[32, 0], [33, 0], [33, 1], [32, 0]],
                    ]],
                ]],
            ],
            'canonical.json' => ['units' => [[
                'id' => 'parish:2', 'name' => 'Example', 'level' => 'parish', 'parent_id' => 'subcounty:2',
            ]]],
            'decisions.json' => [[
                'object_id' => 12, 'unit_id' => 'parish:2', 'parent_unit_id' => 'subcounty:2',
                'evidence_url' => 'https://www.ubos.org/', 'evidence_date' => '2024-11-01',
                'reviewer' => 'internal reviewer',
            ]],
        ];
        try {
            foreach ($inputs as $filename => $value) {
                file_put_contents($directory . '/' . $filename, json_encode($value, JSON_THROW_ON_ERROR));
            }
            $output = $directory . '/verified.geojson';
            $command = escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg(dirname(__DIR__, 2) . '/tools/geo/build-geojson.php') . ' '
                . escapeshellarg($directory . '/source.geojson') . ' '
                . escapeshellarg($directory . '/canonical.json') . ' '
                . escapeshellarg($directory . '/decisions.json') . ' '
                . escapeshellarg($output) . ' OBJECTID source-fixture 2>&1';

            exec($command, $firstOutput, $firstCode);
            $this->assertSame(0, $firstCode, implode("\n", $firstOutput));
            $saved = (string) file_get_contents($output);
            $result = json_decode($saved, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('parish:2', $result['features'][0]['id']);
            $this->assertSame('review_required', $result['metadata']['publication_rights']);
            $this->assertArrayNotHasKey('reviewer', $result['features'][0]['properties']);

            exec($command, $secondOutput, $secondCode);
            $this->assertSame(2, $secondCode);
            $this->assertSame($saved, file_get_contents($output));
        } finally {
            foreach (array_keys($inputs) as $filename) {
                if (is_file($directory . '/' . $filename)) {
                    unlink($directory . '/' . $filename);
                }
            }
            if (is_file($directory . '/verified.geojson')) {
                unlink($directory . '/verified.geojson');
            }
            rmdir($directory);
        }
    }
}
