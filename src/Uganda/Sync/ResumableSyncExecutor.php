<?php

declare(strict_types=1);

namespace Uganda\Sync;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final class ResumableSyncExecutor
{
    public function __construct(
        private PDO $pdo,
        private MappingDefinition $mapping,
        private string $scope = 'default',
    ) {
    }

    /**
     * Commit at most one bounded batch and a matching checkpoint atomically.
     * Earlier committed batches intentionally remain durable after failure.
     *
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $approvedPlan
     * @return array{status: string, plan_hash: string, next_index: int, total_operations: int}
     */
    public function applyNext(array $snapshot, array $approvedPlan, string $approvedHash, int $batchSize = 200): array
    {
        if ($batchSize < 1 || $batchSize > 1000) {
            throw new InvalidArgumentException('Batch size must be between 1 and 1000');
        }
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('Resumable synchronization owns its transaction boundary');
        }

        $catalog = new SnapshotCatalog();
        $catalog->validate($snapshot);
        $sourceHash = $catalog->digest($snapshot);
        $mappingHash = $this->mapping->fingerprint();
        $registry = new IdentityRegistry($this->pdo, $this->mapping, $this->scope);
        $approvedKeys = $this->mapping->strategy() === 'existing' ? $registry->allMappedKeys() : [];
        foreach ($approvedKeys as &$bySource) {
            ksort($bySource);
        }
        unset($bySource);
        ksort($approvedKeys);
        $identityHash = hash('sha256', json_encode($approvedKeys, JSON_THROW_ON_ERROR));

        if (!preg_match('/^[a-f0-9]{64}$/D', $approvedHash) ||
            ($approvedPlan['plan_hash'] ?? null) !== $approvedHash ||
            ($approvedPlan['format_version'] ?? null) !== 1 ||
            ($approvedPlan['scope'] ?? null) !== $this->scope ||
            ($approvedPlan['snapshot_hash'] ?? null) !== $sourceHash ||
            ($approvedPlan['mapping_hash'] ?? null) !== $mappingHash ||
            ($approvedPlan['identity_hash'] ?? null) !== $identityHash ||
            ($approvedPlan['strategy'] ?? null) !== $this->mapping->strategy()) {
            throw new InvalidArgumentException('Approved plan does not match pinned source, identity map or target');
        }
        $withoutHash = $approvedPlan;
        unset($withoutHash['plan_hash']);
        if (!hash_equals(
            $approvedHash,
            hash('sha256', json_encode($withoutHash, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
        )) {
            throw new InvalidArgumentException('Approved plan content differs from its hash');
        }
        $operations = $approvedPlan['operations'] ?? null;
        if (!is_array($operations) || !array_is_list($operations) ||
            !is_array($approvedPlan['conflicts'] ?? null) || $approvedPlan['conflicts'] !== [] ||
            !is_int($approvedPlan['unmapped'] ?? null)) {
            throw new InvalidArgumentException('Approved plan contains unresolved or malformed changes');
        }
        if ($this->mapping->strategy() === 'existing' &&
            $this->mapping->unmappedPolicy() !== 'ignore' && $approvedPlan['unmapped'] > 0) {
            throw new InvalidArgumentException('Existing target has unapproved source identities');
        }
        (new SchemaInspector($this->pdo))->assertMappedColumns($this->mapping);

        $total = count($operations);
        $this->pdo->beginTransaction();
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if (!is_string($driver) || !in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
                throw new RuntimeException('Unsupported synchronization database driver');
            }
            $lock = $driver === 'sqlite' ? '' : ' FOR UPDATE';
            $statement = $this->pdo->prepare('SELECT plan_hash, mapping_scope, snapshot_hash,
                mapping_hash, identity_hash, next_index, total_operations, status
                FROM uganda_geo_sync_checkpoints WHERE plan_hash = ?' . $lock);
            if (!$statement instanceof PDOStatement) {
                throw new RuntimeException('Cannot inspect the synchronization checkpoint');
            }
            $statement->execute([$approvedHash]);
            $checkpoint = $statement->fetch(PDO::FETCH_ASSOC);
            if ($checkpoint === false) {
                $prior = $this->pdo->prepare('SELECT plan_hash FROM uganda_geo_sync_runs WHERE plan_hash = ?');
                $prior->execute([$approvedHash]);
                if ($prior->fetchColumn() !== false) {
                    $this->pdo->commit();
                    return ['status' => 'already_applied', 'plan_hash' => $approvedHash,
                        'next_index' => $total, 'total_operations' => $total];
                }
                $fresh = (new SyncPlanner($this->pdo, $this->mapping, $this->scope))->plan($snapshot);
                if (!hash_equals($approvedHash, $fresh['plan_hash'])) {
                    throw new RuntimeException('Stale approved plan; target records changed before first batch');
                }
                $insert = $this->pdo->prepare('INSERT INTO uganda_geo_sync_checkpoints
                    (plan_hash, mapping_scope, snapshot_hash, mapping_hash, identity_hash,
                     next_index, total_operations, status, updated_at)
                    VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)');
                $insert->execute([$approvedHash, $this->scope, $sourceHash, $mappingHash,
                    $identityHash, $total, 'running', gmdate(DATE_ATOM)]);
                $offset = 0;
            } else {
                foreach (['mapping_scope' => $this->scope, 'snapshot_hash' => $sourceHash,
                    'mapping_hash' => $mappingHash, 'identity_hash' => $identityHash] as $field => $value) {
                    if ($checkpoint[$field] !== $value) {
                        throw new RuntimeException('Checkpoint metadata differs from the approved plan');
                    }
                }
                $position = $checkpoint['next_index'] ?? null;
                $storedTotal = $checkpoint['total_operations'] ?? null;
                if ((!is_int($position) && !is_string($position)) ||
                    (!is_int($storedTotal) && !is_string($storedTotal)) ||
                    !ctype_digit((string) $position) || !ctype_digit((string) $storedTotal)) {
                    throw new RuntimeException('Invalid stored checkpoint position');
                }
                $offset = (int) $position;
                if ($offset < 0 || $offset > $total || (int) $storedTotal !== $total) {
                    throw new RuntimeException('Checkpoint bounds do not match the approved plan');
                }
                if ($checkpoint['status'] === 'completed') {
                    $this->pdo->commit();
                    return ['status' => 'already_applied', 'plan_hash' => $approvedHash,
                        'next_index' => $total, 'total_operations' => $total];
                }
                if ($checkpoint['status'] !== 'running') {
                    throw new RuntimeException('Checkpoint is not in a resumable state');
                }
            }

            $writer = new SyncOperationWriter($this->pdo, $this->mapping, $this->scope);
            $batch = array_slice($operations, $offset, $batchSize);
            foreach ($batch as $operation) {
                if (!is_array($operation)) {
                    throw new RuntimeException('Malformed approved synchronization operation');
                }
                $writer->apply($operation);
            }
            $next = $offset + count($batch);
            $status = $next === $total ? 'completed' : 'running';
            $update = $this->pdo->prepare('UPDATE uganda_geo_sync_checkpoints
                SET next_index = ?, status = ?, updated_at = ?
                WHERE plan_hash = ? AND next_index = ? AND status = ?');
            $update->execute([$next, $status, gmdate(DATE_ATOM), $approvedHash, $offset, 'running']);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Checkpoint moved concurrently; batch was rolled back');
            }
            if ($status === 'completed') {
                $insertRun = $this->pdo->prepare('INSERT INTO uganda_geo_sync_runs
                    (plan_hash, mapping_scope, snapshot_hash, mapping_hash, applied_at, operation_count)
                    VALUES (?, ?, ?, ?, ?, ?)');
                $insertRun->execute([$approvedHash, $this->scope, $sourceHash, $mappingHash,
                    gmdate(DATE_ATOM), $total]);
            }
            $this->pdo->commit();
            return ['status' => $status, 'plan_hash' => $approvedHash,
                'next_index' => $next, 'total_operations' => $total];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * CLI loop, with each batch committed independently. Repeated invocations resume.
     *
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $approvedPlan
     * @return array{status: string, plan_hash: string, next_index: int, total_operations: int}
     */
    public function applyAll(array $snapshot, array $approvedPlan, string $approvedHash, int $batchSize = 200): array
    {
        do {
            $result = $this->applyNext($snapshot, $approvedPlan, $approvedHash, $batchSize);
        } while ($result['status'] === 'running');
        return $result;
    }
}
