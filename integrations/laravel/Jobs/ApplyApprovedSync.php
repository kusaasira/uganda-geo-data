<?php

declare(strict_types=1);

namespace Uganda\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Uganda\Laravel\Runtime;
use Uganda\Sync\SyncExecutor;

final class ApplyApprovedSync implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(
        public string $planPath,
        public string $approvedHash,
    ) {
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('uganda-geo:' . Runtime::scope()))
            ->shared()->releaseAfter(30)->expireAfter(960)];
    }

    public function handle(): void
    {
        $json = file_get_contents($this->planPath);
        if ($json === false) {
            throw new RuntimeException('Approved sync plan is unavailable');
        }
        $plan = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($plan)) {
            throw new RuntimeException('Invalid approved sync plan');
        }
        (new SyncExecutor(Runtime::pdo(), Runtime::mapping(), Runtime::scope()))
            ->apply(Runtime::snapshot(), $plan, $this->approvedHash);
    }
}
