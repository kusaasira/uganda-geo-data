<?php

declare(strict_types=1);

namespace Uganda\Laravel\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Uganda\Laravel\Runtime;
use Uganda\Sync\SyncLease;

final class AbortCommand extends Command
{
    protected $signature = 'uganda-geo:abort
        {--approve= : Exact SHA-256 plan that holds the database lease}
        {--ack-partial : Acknowledge that committed earlier batches remain in the database}';

    protected $description = 'Explicitly abandon a stranded batch run and free its write lease';

    public function handle(): int
    {
        $hash = $this->option('approve');
        if (!is_string($hash) || !$this->option('ack-partial')) {
            throw new RuntimeException('Abort requires --approve=PLAN_HASH --ack-partial');
        }

        $result = (new SyncLease(Runtime::pdo()))->abandon($hash, true);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->warn('Earlier committed batches remain; generate a fresh dry-run plan before further writes');
        return self::SUCCESS;
    }
}
