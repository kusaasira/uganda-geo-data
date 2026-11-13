<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Uganda\Sync\SyncPlanFileWriter;

final class SyncPlanFileWriterTest extends TestCase
{
    #[Test]
    public function privatePlanCreationRefusesOverwriteAndPreservesContents(): void
    {
        $path = sys_get_temp_dir() . '/uganda-geo-plan-' . bin2hex(random_bytes(12)) . '.json';
        try {
            $plan = ['plan_hash' => str_repeat('a', 64), 'target_key' => 'sensitive-local-id'];
            (new SyncPlanFileWriter())->writeNew($plan, $path);
            self::assertSame($plan, json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
            $permissions = fileperms($path);
            self::assertNotFalse($permissions);
            self::assertSame(0, $permissions & 0077, 'Approved plan must never be group or world accessible');

            try {
                (new SyncPlanFileWriter())->writeNew(['plan_hash' => 'different'], $path);
                self::fail('Reviewed plan output must never overwrite an existing file');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('already exists', $exception->getMessage());
            }
            self::assertSame($plan, json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
