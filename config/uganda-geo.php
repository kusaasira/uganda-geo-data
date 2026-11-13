<?php

declare(strict_types=1);

return [
    'connection' => env('UGANDA_GEO_CONNECTION'),
    'mapping_path' => config_path('uganda-geo-mapping.json'),
    'snapshot_path' => env('UGANDA_GEO_SNAPSHOT_PATH'),
    'scope' => env('UGANDA_GEO_MAPPING_SCOPE', 'default'),
    'queue_disk' => env('UGANDA_GEO_SYNC_STORAGE_DISK', 'local'),
];
