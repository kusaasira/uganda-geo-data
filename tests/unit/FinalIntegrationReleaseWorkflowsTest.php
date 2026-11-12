<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class FinalIntegrationReleaseWorkflowsTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itChecksReleaseCandidateAndReadinessOnFinalIntegrationPullRequests(): void
    {
        foreach ([
            '.github/workflows/release-candidate.yml',
            '.github/workflows/release-readiness.yml',
        ] as $path) {
            $workflow = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
            self::assertIsString($workflow, $path);
            self::assertMatchesRegularExpression(
                '/(?m)^  pull_request:\n    branches: \[main, next\/2\.x\]$/',
                $workflow,
                $path . ' must run on the final integration pull request as well as feature pull requests'
            );
            self::assertStringContainsString("permissions:\n  contents: read", $workflow, $path);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itDocumentsTheActualReadinessEnforcementFlag(): void
    {
        $command = file_get_contents(dirname(__DIR__, 2) . '/tools/release/assess-readiness.php');
        self::assertIsString($command);
        self::assertStringContainsString(
            'Usage: php tools/release/assess-readiness.php EVIDENCE.json [--require-attestations]',
            $command
        );
        self::assertStringNotContainsString('EVIDENCE.json [--enforce]', $command);
    }
}
