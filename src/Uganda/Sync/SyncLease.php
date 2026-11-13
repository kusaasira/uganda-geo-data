<?php

declare(strict_types=1);

namespace Uganda\Sync;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Database-wide durable ownership of the synchronization write surface.
 *
 * Always acquire inside the transaction that writes geography or checkpoint rows.
 * A checkpointed run retains ownership between transactions until completion or
 * a separate explicit administrative abandonment operation.
 */
final class SyncLease
{
    private const LOCK_NAME = 'uganda_geo_database';

    public function __construct(private PDO $pdo)
    {
    }

    public function acquire(string $planHash): void
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('Synchronization lease requires an active transaction');
        }
        self::assertHash($planHash);
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!is_string($driver) || !in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
            throw new RuntimeException('Unsupported synchronization lease database driver');
        }
        $locking = $driver === 'sqlite' ? '' : ' FOR UPDATE';
        $query = $this->pdo->prepare('SELECT plan_hash FROM uganda_geo_sync_leases
            WHERE lock_name = ?' . $locking);
        if (!$query instanceof PDOStatement) {
            throw new RuntimeException('Unable to inspect synchronization lease');
        }
        $query->execute([self::LOCK_NAME]);
        $owner = $query->fetchColumn();
        if ($owner !== false) {
            if (!is_string($owner) || !hash_equals($owner, $planHash)) {
                throw new RuntimeException('Another geographic synchronization owns this database');
            }
            return;
        }
        $insert = $this->pdo->prepare('INSERT INTO uganda_geo_sync_leases
            (lock_name, plan_hash, acquired_at) VALUES (?, ?, ?)');
        $insert->execute([self::LOCK_NAME, $planHash, gmdate(DATE_ATOM)]);
    }

    public function release(string $planHash): void
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('Synchronization lease release requires an active transaction');
        }
        self::assertHash($planHash);
        $delete = $this->pdo->prepare('DELETE FROM uganda_geo_sync_leases
            WHERE lock_name = ? AND plan_hash = ?');
        $delete->execute([self::LOCK_NAME, $planHash]);
        if ($delete->rowCount() !== 1) {
            throw new RuntimeException('Cannot release a synchronization lease owned by another plan');
        }
    }

    /** @return array{plan_hash: string, acquired_at: string}|null */
    public function active(): ?array
    {
        $stmt = $this->pdo->prepare('SELECT plan_hash, acquired_at FROM uganda_geo_sync_leases
            WHERE lock_name = ?');
        $stmt->execute([self::LOCK_NAME]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        if (!is_string($row['plan_hash'] ?? null) || !is_string($row['acquired_at'] ?? null)) {
            throw new RuntimeException('Corrupt synchronization lease metadata');
        }
        return ['plan_hash' => $row['plan_hash'], 'acquired_at' => $row['acquired_at']];
    }

    public static function assertHash(string $planHash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $planHash) !== 1) {
            throw new RuntimeException('Invalid synchronization plan digest');
        }
    }
}
