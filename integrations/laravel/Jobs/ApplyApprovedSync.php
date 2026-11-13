<?php

declare(strict_types=1);

namespace Uganda\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
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
        public string $disk,
        public string $artifactPrefix,
        public string $approvedHash,
        public string $scope,
    ) {
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('uganda-geo:' . $this->scope))
            ->shared()->releaseAfter(30)->expireAfter(960)];
    }

    public function handle(): void
    {
        if (Runtime::scope() !== $this->scope) {
            throw new RuntimeException('Queue worker scope differs from the approved synchronization scope');
        }
        $filesystem = Storage::disk($this->disk);
        $planJson = $filesystem->get($this->artifactPrefix . '/plan.json');
        $snapshotJson = $filesystem->get($this->artifactPrefix . '/snapshot.json');
        if (!is_string($planJson) || !is_string($snapshotJson)) {
            throw new RuntimeException('Pinned approved synchronization artifacts are unavailable');
        }
        $plan = json_decode($planJson, true, 512, JSON_THROW_ON_ERROR);
        $snapshot = json_decode($snapshotJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($plan) || !is_array($snapshot)) {
            throw new RuntimeException('Invalid pinned synchronization artifacts');
        }
        if (!isset($plan['plan_hash']) || !is_string($plan['plan_hash']) ||
            !hash_equals($this->approvedHash, $plan['plan_hash'])) {
            throw new RuntimeException('Pinned plan does not match its approval token');
        }
        (new SyncExecutor(Runtime::pdo(), Runtime::mapping(), $this->scope))
            ->apply($snapshot, $plan, $this->approvedHash);
    }
}
