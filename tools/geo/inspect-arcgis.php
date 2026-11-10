<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Uganda/Geo/ArcGisLayerMetadata.php';

$catalogue = dirname(__DIR__, 2) . '/resources/geometry/endpoints.json';
$id = $argv[1] ?? 'arcgis-parishes-2024';
if ($id === '' || !preg_match('/^[a-z0-9-]+$/', $id)) {
    fwrite(STDERR, "Invalid endpoint ID\n");
    exit(2);
}
try {
    $raw = file_get_contents($catalogue);
    if ($raw === false) {
        throw new RuntimeException('Endpoint registry is unreadable');
    }
    $registry = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $matched = null;
    foreach ($registry['endpoints'] ?? [] as $endpoint) {
        if (($endpoint['id'] ?? null) === $id) {
            $matched = $endpoint;
            break;
        }
    }
    if ($matched === null) {
        throw new RuntimeException('Unknown endpoint ID');
    }
    $url = $matched['url'];
    $host = parse_url($url, PHP_URL_HOST);
    if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array($host, ['services1.arcgis.com', 'services.arcgis.com'], true)) {
        throw new RuntimeException('Endpoint is not on the approved HTTPS host allowlist');
    }
    $separator = strpos($url, '?') === false ? '?' : '&';
    $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 15, 'follow_location' => 0, 'ignore_errors' => false, 'header' => 'Accept: application/json']]);
    $response = @file_get_contents($url . $separator . 'f=pjson', false, $context, 0, 1048577);
    if ($response === false || strlen($response) > 1048576) {
        throw new RuntimeException('Layer metadata request failed or exceeded 1 MiB');
    }
    $metadata = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($metadata)) {
        throw new RuntimeException('Unexpected FeatureServer layer response');
    }
    \Uganda\Geo\ArcGisLayerMetadata::assertMatches($metadata, $matched);
    $output = [
        'endpoint_id' => $id,
        'url' => $url,
        'checked_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'sha256' => hash('sha256', $response),
        'object_id_field' => $metadata['objectIdField'] ?? null,
        'max_record_count' => $metadata['maxRecordCount'] ?? null,
        'geometry_type' => $metadata['geometryType'],
        'supported_query_formats' => $metadata['supportedQueryFormats'] ?? null,
        'spatial_reference' => $metadata['extent']['spatialReference'] ?? null,
        'redistribution_status' => $matched['redistribution_status']
    ];
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Endpoint inspection failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
