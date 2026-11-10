<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StandaloneExampleTest extends TestCase
{
    /** @test */
    public function documentedStandaloneExampleReturnsValidLookupData(): void
    {
        $output = [];
        $status = 0;
        exec(escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(dirname(__DIR__, 2) . '/examples/standalone-v2.php') . ' 2>&1',
            $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
        $payload = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['matched_by_id']);
        self::assertGreaterThan(0, $payload['name_search_matches']);
        self::assertGreaterThanOrEqual(0, $payload['county_count']);
        self::assertIsInt($payload['district']['id']);
    }
}
