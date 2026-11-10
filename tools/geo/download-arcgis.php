<?php

declare(strict_types=1);

use Uganda\Geo\ArcGisFeatureImporter;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Geo/GeometryContract.php';
require_once $root . '/src/Uganda/Geo/ArcGisFeatureImporter.php';

/** @return array{body: string, headers: array<int, string>} */
function fetchArcGisJson(string $url, int $maxBytes): array
{
    $host = parse_url($url, PHP_URL_HOST);
    if (parse_url($url, PHP_URL_SCHEME) !== 'https' ||
        !in_array($host, ['services1.arcgis.com', 'services.arcgis.com'], true)) {
        throw new RuntimeException('ArcGIS URL is outside the HTTPS host allowlist');
    }
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => 25,
        'follow_location' => 0,
        'ignore_errors' => false,
        'header' => "Accept: application/json\r\nUser-Agent: UgandaGeo-SourceAcquisition/2.x",
    ]]);
    $body = @file_get_contents($url, false, $context, 0, $maxBytes + 1);
    if ($body === false || strlen($body) > $maxBytes) {
        throw new RuntimeException('ArcGIS request failed or exceeded the permitted response size');
    }
    return ['body' => $body, 'headers' => $http_response_header ?? []];
}

function writeImmutableFile(string $file, string $contents): void
{
    if (file_exists($file) || file_put_contents($file, $contents, LOCK_EX) !== strlen($contents)) {
        throw new RuntimeException('Unable to write complete immutable acquisition file');
    }
}

$id = $argv[1] ?? null;
$destination = $argv[2] ?? null;
if ($id === null || $destination === null || !preg_match('/^[a-z0-9-]+$/', $id)) {
    fwrite(STDERR, "Usage: php tools/geo/download-arcgis.php <endpoint-id> <new-directory>\n");
    exit(2);
}

try {
    $catalogue = json_decode((string) file_get_contents($root . '/resources/geometry/endpoints.json'), true, 512, JSON_THROW_ON_ERROR);
    $endpoint = null;
    foreach ($catalogue['endpoints'] ?? [] as $candidate) {
        if (($candidate['id'] ?? null) === $id) {
            $endpoint = $candidate;
            break;
        }
    }
    if ($endpoint === null || !is_string($endpoint['object_id_field']) || $endpoint['object_id_field'] === '') {
        throw new RuntimeException('Unknown endpoint or missing object ID field');
    }
    if (file_exists($destination)) {
        throw new RuntimeException('Destination exists; choose a new directory for an immutable acquisition');
    }
    $parent = dirname($destination);
    if (!is_dir($parent) || !is_writable($parent)) {
        throw new RuntimeException('Destination parent must exist and be writable');
    }

    $base = $endpoint['url'] . '/query';
    $idQuery = http_build_query(['where' => '1=1', 'returnIdsOnly' => 'true', 'f' => 'json'], '', '&', PHP_QUERY_RFC3986);
    $idResponse = fetchArcGisJson($base . '?' . $idQuery, 4000000);
    $idsPayload = $idResponse['body'];
    $idsResponse = json_decode($idsPayload, true, 512, JSON_THROW_ON_ERROR);
    $objectIds = $idsResponse['objectIds'] ?? null;
    if (!is_array($objectIds) || $objectIds === [] || isset($idsResponse['error']) ||
        array_keys($objectIds) !== array_keys(array_values($objectIds))) {
        throw new RuntimeException('ArcGIS returned an empty or invalid object ID inventory');
    }
    foreach ($objectIds as $oid) {
        if (!is_int($oid) || $oid < 0) {
            throw new RuntimeException('Invalid ArcGIS integer object ID');
        }
    }
    $ids = $objectIds;
    sort($ids, SORT_NUMERIC);
    if (count($ids) !== count(array_unique($ids))) {
        throw new RuntimeException('Duplicate object IDs in service inventory');
    }

    $stage = $destination . '.partial-' . bin2hex(random_bytes(4));
    if (!mkdir($stage, 0775)) {
        throw new RuntimeException('Unable to create staging directory');
    }

    writeImmutableFile($stage . '/object-ids.json', $idsPayload);
    $seen = [];
    $pages = [];
    $reader = new ArcGisFeatureImporter();
    foreach (array_chunk($ids, 100) as $pageNumber => $chunk) {
        $params = [
            'objectIds' => implode(',', $chunk),
            'outFields' => '*',
            'returnGeometry' => 'true',
            'outSR' => '4326',
            'f' => 'geojson',
        ];
        $url = $base . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $pageResponse = fetchArcGisJson($url, 50000000);
        $raw = $pageResponse['body'];
        $features = $reader->parsePage($raw, $endpoint['object_id_field']);
        $expected = array_map('strval', $chunk);
        $actual = array_map('strval', array_keys($features));
        sort($expected, SORT_NUMERIC);
        sort($actual, SORT_NUMERIC);
        if ($actual !== $expected) {
            throw new RuntimeException("Page $pageNumber returned missing or unexpected object IDs");
        }
        foreach ($actual as $featureId) {
            if (isset($seen[$featureId])) {
                throw new RuntimeException("Duplicate feature $featureId across pages");
            }
            $seen[$featureId] = true;
        }
        $file = sprintf('page-%05d.geojson', $pageNumber + 1);
        writeImmutableFile($stage . '/' . $file, $raw);
        $pages[] = [
            'file' => $file,
            'request_url' => $url,
            'response_headers' => $pageResponse['headers'],
            'object_ids' => $chunk,
            'sha256' => hash('sha256', $raw),
            'byte_count' => strlen($raw),
        ];
        usleep(150000);
    }
    if (count($seen) !== count($ids) || $pages === []) {
        throw new RuntimeException('Fetched feature inventory does not match source object ID count');
    }
    $manifest = [
        'endpoint_id' => $id,
        'source_url' => $endpoint['url'],
        'captured_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'object_id_field' => $endpoint['object_id_field'],
        'feature_count' => count($seen),
        'source_crs' => $endpoint['source_crs'],
        'output_crs' => 'EPSG:4326',
        'redistribution_status' => $endpoint['redistribution_status'],
        'object_id_inventory_file' => 'object-ids.json',
        'object_id_inventory_url' => $base . '?' . $idQuery,
        'object_id_inventory_headers' => $idResponse['headers'],
        'object_id_inventory_sha256' => hash('sha256', $idsPayload),
        'pages' => $pages,
    ];
    writeImmutableFile($stage . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    if (!rename($stage, $destination)) {
        throw new RuntimeException('Unable to atomically publish completed acquisition');
    }
    fwrite(STDOUT, 'Acquired ' . count($seen) . ' polygon features into ' . $destination . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'ArcGIS acquisition failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
