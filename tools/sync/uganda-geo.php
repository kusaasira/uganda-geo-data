<?php

declare(strict_types=1);

use Uganda\Sync\IdentityRegistry;
use Uganda\Sync\MappingDefinition;
use Uganda\Sync\SchemaInspector;
use Uganda\Sync\SchemaInstaller;
use Uganda\Sync\SnapshotCatalog;
use Uganda\Sync\SnapshotExporter;
use Uganda\Sync\SyncExecutor;
use Uganda\Sync\ResumableSyncExecutor;
use Uganda\Sync\SyncPlanner;
use Uganda\Sync\SyncLease;

$packageRoot = dirname(__DIR__, 2);
$checkoutAutoload = $packageRoot . '/vendor/autoload.php';
$consumerAutoload = dirname($packageRoot, 2) . '/autoload.php';
$autoload = is_file($checkoutAutoload) ? $checkoutAutoload : $consumerAutoload;
if (!is_file($autoload)) {
    fwrite(STDERR, "Unable to locate Composer autoloader. Install the package dependencies first.\n");
    exit(1);
}
require_once $autoload;

$args = [];
foreach (array_slice($argv, 2) as $argument) {
    if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
        fwrite(STDERR, "Expected --key=value options\n");
        exit(2);
    }
    [$key, $value] = explode('=', substr($argument, 2), 2);
    if (isset($args[$key])) {
        fwrite(STDERR, "Duplicate CLI option\n");
        exit(2);
    }
    $args[$key] = $value;
}
$action = $argv[1] ?? '';
$usage = "Usage: php tools/sync/uganda-geo.php <discover|install|plan|apply|approve-map|export|status|abort> [--mapping=file.json] [--snapshot=file.json] [--output=path] [--plan=file.json] [--approve=SHA256] [--batch-size=200]\n";
if (!in_array($action, ['discover', 'install', 'plan', 'apply', 'approve-map', 'export', 'status', 'abort'], true)) {
    fwrite(STDERR, $usage);
    exit(2);
}
try {
    $catalog = new SnapshotCatalog();
    $snapshot = in_array($action, ['export', 'plan', 'apply'], true)
        ? (isset($args['snapshot']) ? $catalog->fromFile($args['snapshot']) : $catalog->bundled())
        : [];
    $mapping = MappingDefinition::managed();
    if (isset($args['mapping'])) {
        $raw = file_get_contents($args['mapping']);
        if ($raw === false) {
            throw new RuntimeException('Cannot read mapping configuration');
        }
        $config = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($config)) {
            throw new RuntimeException('Mapping must be a JSON object');
        }
        $mapping = new MappingDefinition($config);
    }
    $scope = $args['scope'] ?? 'default';
    if ($action === 'export') {
        $path = $args['output'] ?? '';
        if ($path === '') {
            throw new RuntimeException('Export requires --output=filename');
        }
        $result = (new SnapshotExporter())->export($snapshot, $args['format'] ?? 'json', $path);
    } else {
        $dsn = getenv('UGANDA_GEO_DSN');
        if ($dsn === false || $dsn === '') {
            throw new RuntimeException('Provide UGANDA_GEO_DSN through the environment');
        }
        $pdo = new PDO($dsn, getenv('UGANDA_GEO_DB_USER') ?: null, getenv('UGANDA_GEO_DB_PASSWORD') ?: null);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($action === 'discover') {
            $result = (new SchemaInspector($pdo))->candidates();
        } elseif ($action === 'install') {
            (new SchemaInstaller($pdo))->install();
            $result = ['status' => 'installed', 'note' => 'Database tables installed only after explicit request'];
        } elseif ($action === 'plan') {
            $result = (new SyncPlanner($pdo, $mapping, $scope))->plan($snapshot);
            if (isset($args['output'])) {
                $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                $file = @fopen($args['output'], 'x');
                if ($file === false) {
                    throw new RuntimeException('Plan file already exists or is not writable');
                }
                try {
                    if (fwrite($file, $json) !== strlen($json)) {
                        throw new RuntimeException('Incomplete plan write');
                    }
                } finally {
                    fclose($file);
                }
            }
        } elseif ($action === 'apply') {
            if (!isset($args['plan'], $args['approve'])) {
                throw new RuntimeException('Apply requires --plan=file.json and --approve=PLAN_HASH');
            }
            $json = file_get_contents($args['plan']);
            if ($json === false) {
                throw new RuntimeException('Cannot read approved plan');
            }
            $approved = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($approved)) {
                throw new RuntimeException('Invalid approved plan');
            }
            if (isset($args['batch-size'])) {
                $batchSize = filter_var($args['batch-size'], FILTER_VALIDATE_INT);
                if (!is_int($batchSize) || $batchSize < 1 || $batchSize > 1000) {
                    throw new RuntimeException('Batch size must be an integer between 1 and 1000');
                }
                $result = (new ResumableSyncExecutor($pdo, $mapping, $scope))
                    ->applyAll($snapshot, $approved, $args['approve'], $batchSize);
            } else {
                $result = (new SyncExecutor($pdo, $mapping, $scope))
                    ->apply($snapshot, $approved, $args['approve']);
            }
        } elseif ($action === 'abort') {
            $hash = $args['approve'] ?? '';
            if (!is_string($hash) || ($args['ack-partial'] ?? null) !== 'yes') {
                throw new RuntimeException('Abort requires --approve=PLAN_HASH --ack-partial=yes; committed rows will remain');
            }
            $result = (new SyncLease($pdo))->abandon($hash, true);
        } elseif ($action === 'approve-map') {
            foreach (['source', 'level', 'target', 'reviewer'] as $key) {
                if (!isset($args[$key])) {
                    throw new RuntimeException('Mapping approval requires --source, --level, --target and --reviewer');
                }
            }
            (new IdentityRegistry($pdo, $mapping, $scope))->approve(
                $args['source'], $args['level'], $args['target'], $args['reviewer']
            );
            $result = ['status' => 'mapped', 'source_key' => $args['source']];
        } else {
            $query = $pdo->query('SELECT plan_hash, snapshot_hash, mapping_hash, applied_at, operation_count FROM uganda_geo_sync_runs ORDER BY applied_at DESC LIMIT 20');
            $checkpointQuery = $pdo->query('SELECT plan_hash, mapping_scope, next_index, total_operations, status, updated_at FROM uganda_geo_sync_checkpoints ORDER BY updated_at DESC LIMIT 20');
            $result = ['runs' => $query->fetchAll(PDO::FETCH_ASSOC),
                'checkpoints' => $checkpointQuery->fetchAll(PDO::FETCH_ASSOC),
                'active_lease' => (new SyncLease($pdo))->active()];
        }
    }
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . "\n");
    exit(1);
}
