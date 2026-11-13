<?php

declare(strict_types=1);

namespace Uganda\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Uganda\Laravel\Jobs\ApplyApprovedSync;
use Uganda\Laravel\Runtime;
use Uganda\Sync\SyncExecutor;
use Uganda\Sync\ResumableSyncExecutor;
use Uganda\Sync\SyncPlanner;
use Uganda\Sync\SyncPlanFileWriter;

final class SyncCommand extends Command
{
    protected $signature = 'uganda-geo:sync
        {--output= : Write a new dry-run plan file}
        {--plan= : Approved plan file for explicit apply}
        {--approve= : Exact SHA-256 plan approval token}
        {--queue : Dispatch approved apply using the configured Laravel queue}
        {--batch-size= : Execute durable 1-1000-operation batches and resume on retry}';

    protected $description = 'Preview or explicitly apply an identity-safe geographic synchronization plan';

    public function handle(): int
    {
        $mapping = Runtime::mapping();
        $snapshot = Runtime::snapshot();
        $scope = Runtime::scope();
        $planPath = $this->option('plan');
        $approval = $this->option('approve');
        if ($planPath === null && $approval === null) {
            if ($this->option('queue')) {
                throw new RuntimeException('Queueing requires an approved saved plan');
            }
            $plan = (new SyncPlanner(Runtime::pdo(), $mapping, $scope))->plan($snapshot);
            if ($this->option('output')) {
                (new SyncPlanFileWriter())->writeNew($plan, $this->option('output'));
            }
            $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }
        if (!is_string($planPath) || $planPath === '' || !is_string($approval) || $approval === '') {
            throw new RuntimeException('Applying requires both --plan and --approve');
        }
        $json = file_get_contents($planPath);
        if ($json === false) {
            throw new RuntimeException('Cannot read approved plan');
        }
        $plan = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($plan)) {
            throw new RuntimeException('Invalid plan document');
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', $approval) ||
            !isset($plan['plan_hash']) || !is_string($plan['plan_hash']) ||
            !hash_equals($plan['plan_hash'], $approval)) {
            throw new RuntimeException('Saved plan does not match its explicit approval token');
        }
        $requestedBatch = $this->option('batch-size');
        $batchSize = $requestedBatch === null ? 200 : filter_var($requestedBatch, FILTER_VALIDATE_INT);
        if (!is_int($batchSize) || $batchSize < 1 || $batchSize > 1000) {
            throw new RuntimeException('Batch size must be an integer between 1 and 1000');
        }
        if ($this->option('queue')) {
            $disk = config('uganda-geo.queue_disk', 'local');
            if (!is_string($disk) || $disk === '') {
                throw new RuntimeException('Queue storage disk must be configured');
            }
            $filesystem = Storage::disk($disk);
            $prefix = 'uganda-geo/approved/' . $approval;
            $artifacts = [
                $prefix . '/plan.json' => json_encode($plan, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                $prefix . '/snapshot.json' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ];
            foreach ($artifacts as $path => $content) {
                if ($filesystem->exists($path)) {
                    $existing = $filesystem->get($path);
                    if (!is_string($existing) ||
                        !hash_equals(hash('sha256', $content), hash('sha256', $existing))) {
                        throw new RuntimeException('An immutable sync artifact was unexpectedly changed');
                    }
                } elseif (!$filesystem->put($path, $content, ['visibility' => 'private'])) {
                    throw new RuntimeException('Failed to pin approved sync artifacts to the private queue disk');
                }
            }
            ApplyApprovedSync::dispatch($disk, $prefix, $approval, $scope, $batchSize)->afterCommit();
            $this->info('Approved sync dispatched with pinned private source and plan artifacts');
            return self::SUCCESS;
        }
        if ($requestedBatch !== null) {
            $report = (new ResumableSyncExecutor(Runtime::pdo(), $mapping, $scope))
                ->applyAll($snapshot, $plan, $approval, $batchSize);
        } else {
            $report = (new SyncExecutor(Runtime::pdo(), $mapping, $scope))
                ->apply($snapshot, $plan, $approval);
        }
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
