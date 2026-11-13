<?php

declare(strict_types=1);

namespace Uganda\Sync;

use PDO;
use RuntimeException;

final class SchemaInstaller
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Explicit setup only. Never invoke from package boot, discovery or a dry-run.
     * Schema DDL must not execute inside an application transaction.
     */
    public function install(): void
    {
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('Install synchronization tables outside a transaction');
        }
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
            throw new RuntimeException('Unsupported PDO driver');
        }
        $engine = $driver === 'mysql' ? ' ENGINE=InnoDB' : '';
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS uganda_geo_units (
            source_hash CHAR(64) NOT NULL PRIMARY KEY,
            source_key TEXT NOT NULL,
            level VARCHAR(40) NOT NULL,
            name TEXT NOT NULL,
            parent_hash CHAR(64) NULL,
            snapshot_hash CHAR(64) NOT NULL
        )' . $engine);
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS uganda_geo_identity_maps (
            mapping_scope VARCHAR(80) NOT NULL,
            source_hash CHAR(64) NOT NULL,
            source_key TEXT NOT NULL,
            level VARCHAR(40) NOT NULL,
            target_table VARCHAR(63) NOT NULL,
            target_key VARCHAR(191) NOT NULL,
            reviewer VARCHAR(191) NOT NULL,
            approved INTEGER NOT NULL,
            PRIMARY KEY (mapping_scope, source_hash, target_table),
            UNIQUE (mapping_scope, level, target_table, target_key)
        )' . $engine);
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS uganda_geo_sync_runs (
            plan_hash CHAR(64) NOT NULL PRIMARY KEY,
            mapping_scope VARCHAR(80) NOT NULL,
            snapshot_hash CHAR(64) NOT NULL,
            mapping_hash CHAR(64) NOT NULL,
            applied_at VARCHAR(40) NOT NULL,
            operation_count INTEGER NOT NULL
        )' . $engine);
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS uganda_geo_sync_checkpoints (
            plan_hash CHAR(64) NOT NULL PRIMARY KEY,
            mapping_scope VARCHAR(80) NOT NULL,
            snapshot_hash CHAR(64) NOT NULL,
            mapping_hash CHAR(64) NOT NULL,
            identity_hash CHAR(64) NOT NULL,
            next_index INTEGER NOT NULL,
            total_operations INTEGER NOT NULL,
            status VARCHAR(20) NOT NULL,
            updated_at VARCHAR(40) NOT NULL
        )' . $engine);
    }
}
