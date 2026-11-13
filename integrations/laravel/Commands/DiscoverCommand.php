<?php

declare(strict_types=1);

namespace Uganda\Laravel\Commands;

use Illuminate\Console\Command;
use Uganda\Laravel\Runtime;
use Uganda\Sync\SchemaInspector;

final class DiscoverCommand extends Command
{
    protected $signature = 'uganda-geo:discover {--connection= : Existing Laravel connection name}';

    protected $description = 'Inspect candidate geography tables without reading or modifying application records';

    public function handle(): int
    {
        $pdo = Runtime::pdo($this->option('connection') ?: null);
        $candidates = (new SchemaInspector($pdo))->candidates();
        $this->line(json_encode($candidates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->info('Suggestions only. Configure exact tables and identity keys before any write.');
        return self::SUCCESS;
    }
}
