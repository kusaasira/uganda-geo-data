<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\SoftwareBillOfMaterials;

final class SoftwareBillOfMaterialsTest extends TestCase
{
    /** @test */
    public function itRecordsOnlyProductionDependenciesDeterministically(): void
    {
        $composer = [
            'name' => 'kusaasira/uganda-geo',
            'license' => 'MIT',
            'require' => ['ext-json' => '*', 'php' => '^8.2'],
            'require-dev' => ['phpunit/phpunit' => '^9'],
        ];
        $builder = new SoftwareBillOfMaterials();
        $bom = $builder->build($composer, null, '2.0.0-dev');
        self::assertSame('CycloneDX', $bom['bomFormat']);
        self::assertSame(['ext-json', 'php'], array_column($bom['components'], 'name'));
        self::assertSame($bom, $builder->build($composer, null, '2.0.0-dev'));
    }

    /** @test */
    public function itRequiresLockedVersionsOfThirdPartyProductionDependencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SoftwareBillOfMaterials())->build([
            'name' => 'kusaasira/uganda-geo',
            'license' => 'MIT',
            'require' => ['vendor/external' => '^1'],
        ], null, '2.0.0');
    }
    /** @test */
    public function itIncludesTransitiveLockedRuntimeDependencies(): void
    {
        $composer = [
            'name' => 'kusaasira/uganda-geo',
            'license' => 'MIT',
            'require' => ['vendor/direct' => '^1'],
        ];
        $lock = ['packages' => [
            ['name' => 'vendor/transitive', 'version' => '1.4.0', 'license' => ['MIT']],
            ['name' => 'vendor/direct', 'version' => '1.2.0', 'license' => ['MIT']],
        ], 'packages-dev' => [
            ['name' => 'vendor/development', 'version' => '9.0.0'],
        ]];
        $bom = (new SoftwareBillOfMaterials())->build($composer, $lock, '2.0.0-dev');
        self::assertSame(
            ['vendor/direct', 'vendor/transitive'],
            array_column($bom['components'], 'name')
        );
    }
}
