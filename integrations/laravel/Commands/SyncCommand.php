<?php

declare(strict_types=1);

namespace Uganda\Laravel\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Uganda\Laravel\Jobs\ApplyApprovedSync;
use Uganda\Laravel\Runtime;
use Uganda\Sync\SyncExecutor;
use Uganda\Sync\SyncPlanner;

final class SyncCommand extends Command
{
    protected $signature = 'uganda-geo:sync
        {--output= : Write a new dry-run plan file}
        {--plan= : Approved plan file for explicit apply}
        {--approve= : Exact SHA-256 plan approval token}
        {--queue : Dispatch approved apply using the configured Laravel queue}';

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
                $path = $this->option('output');
                $handle = @fopen($path, 'x');
                if ($handle === false) {
                    throw new RuntimeException('Plan file must not already exist');
                }
                try {
                    $json = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                    if (fwrite($handle, $json) !== strlen($json)) {
                        throw new RuntimeException('Incomplete plan write');
                    }
                } finally {
                    fclose($handle);
                }
            }
            $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }
        if (!is_string($planPath) || $planPath === '' || !is_string($approval) || $approval === '') {
            throw new RuntimeException('Applying requires both --plan and --approve');
        }
        if ($this->option('queue')) {
            ApplyApprovedSync::dispatch($planPath, $approval)->afterCommit();
            $this->info('Approved plan dispatched to the configured queue');
            return self::SUCCESS;
        }
        $json = file_get_contents($planPath);
        if ($json === false) {
            throw new RuntimeException('Cannot read approved plan');
        }
        $plan = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($plan)) {
            throw new RuntimeException('Invalid plan document');
        }
        $report = (new SyncExecutor(Runtime::pdo(), $mapping, $scope))->apply($snapshot, $plan, $approval);
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
