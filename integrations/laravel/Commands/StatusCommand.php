<?php

declare(strict_types=1);

namespace Uganda\Laravel\Commands;

use Illuminate\Console\Command;
use PDO;
use Uganda\Laravel\Runtime;
use Uganda\Sync\SyncLease;

final class StatusCommand extends Command
{
    protected $signature = 'uganda-geo:status';

    protected $description = 'Show recent synchronization runs recorded on the configured connection';

    public function handle(): int
    {
        $query = Runtime::pdo()->query('SELECT plan_hash, snapshot_hash, mapping_hash, applied_at, operation_count FROM uganda_geo_sync_runs ORDER BY applied_at DESC LIMIT 20');
        $pending = Runtime::pdo()->query('SELECT plan_hash, mapping_scope, next_index, total_operations,
            status, updated_at FROM uganda_geo_sync_checkpoints ORDER BY updated_at DESC LIMIT 20');
        $this->line(json_encode([
            'completed_runs' => $query->fetchAll(PDO::FETCH_ASSOC),
            'checkpoints' => $pending->fetchAll(PDO::FETCH_ASSOC),
            'active_lease' => (new SyncLease(Runtime::pdo()))->active(),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
