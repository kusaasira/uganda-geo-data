<?php

declare(strict_types=1);

/** Run an isolated Composer installation of the release candidate. */
$root = realpath(dirname(__DIR__, 2));
if ($root === false) {
    fwrite(STDERR, "Unable to resolve repository root\n");
    exit(1);
}
if ($argc > 2 || (isset($argv[1]) && $argv[1] !== '--laravel')) {
    fwrite(STDERR, "Usage: php tools/ci/consumer-install.php [--laravel]\n");
    exit(2);
}
$laravel = ($argv[1] ?? '') === '--laravel';
$directory = sys_get_temp_dir() . '/uganda-geo-consumer-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) {
    fwrite(STDERR, "Unable to create isolated consumer directory\n");
    exit(1);
}

function cleanConsumer(string $path): void
{
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

try {
    $requirements = ['kusaasira/uganda-geo' => '^2.0'];
    if ($laravel) {
        $requirements['laravel/framework'] = '^12.0';
    }
    $data = [
        'name' => 'uganda-geo/isolated-consumer',
        'type' => 'project',
        'repositories' => [[
            'type' => 'path',
            'url' => $root,
            'options' => ['symlink' => false, 'versions' => ['kusaasira/uganda-geo' => '2.0.1']],
        ]],
        'require' => $requirements,
        'config' => ['allow-plugins' => false],
    ];
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($directory . '/composer.json', $json . "\n") === false) {
        throw new RuntimeException('Cannot prepare Composer manifest');
    }
    $original = getcwd();
    if (!chdir($directory)) {
        throw new RuntimeException('Cannot enter the consumer directory');
    }
    try {
        $status = 0;
        passthru('composer install --no-interaction --no-plugins --no-scripts --no-progress --prefer-dist 2>&1', $status);
        if ($status !== 0) {
            throw new RuntimeException('Composer installation failed');
        }
        require $directory . '/vendor/autoload.php';
        $uganda = new Uganda\Uganda();
        $districts = $uganda->districts();
        $first = reset($districts);
        if (!$first instanceof Uganda\District || $uganda->findById('district', $first->id()) === null) {
            throw new RuntimeException('Installed package lookup failed');
        }
        $bin = $directory . '/vendor/bin/uganda-geo';
        if (!is_file($bin)) {
            throw new RuntimeException('Composer did not install Uganda Geo standalone executable');
        }
        $output = $directory . '/consumer-snapshot.json';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin)
            . ' export --format=json --output=' . escapeshellarg($output) . ' 2>&1';
        $lines = [];
        $status = 0;
        exec($command, $lines, $status);
        if ($status !== 0 || !is_file($output)) {
            throw new RuntimeException('Installed CLI export failed: ' . implode("\\n", $lines));
        }
        $export = json_decode((string) file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($export['units']) || !is_array($export['units']) || $export['units'] === []) {
            throw new RuntimeException('Installed CLI exported no geography records');
        }
        if ($laravel) {
            $container = new Illuminate\Container\Container();
            if (!$container->make(Uganda\Uganda::class) instanceof Uganda\Uganda) {
                throw new RuntimeException('Laravel container integration failed');
            }
            if (!class_exists(Uganda\Laravel\UgandaGeoServiceProvider::class)) {
                throw new RuntimeException('Optional Laravel service provider is not Composer autoloadable');
            }
            $application = new Illuminate\Foundation\Application($directory);
            $application->instance('config', new Illuminate\Config\Repository([
                'uganda-geo' => [],
                'database' => [
                    'default' => 'sqlite',
                    'connections' => [
                        'sqlite' => [
                            'driver' => 'sqlite',
                            'database' => ':memory:',
                            'prefix' => '',
                        ],
                    ],
                ],
            ]));
            $application->register(Illuminate\Database\DatabaseServiceProvider::class);
            $application->register(Uganda\Laravel\UgandaGeoServiceProvider::class);
            $application->boot();
            if (!$application->getProvider(Uganda\Laravel\UgandaGeoServiceProvider::class)) {
                throw new RuntimeException('Laravel package provider registration failed');
            }
            Illuminate\Support\Facades\Facade::setFacadeApplication($application);
            $discover = $application->make(Uganda\Laravel\Commands\DiscoverCommand::class);
            $discover->setLaravel($application);
            $discoverOutput = new Symfony\Component\Console\Output\BufferedOutput();
            $discoveryStatus = $discover->run(
                new Symfony\Component\Console\Input\ArrayInput(['--connection' => 'sqlite']),
                $discoverOutput
            );
            if ($discoveryStatus !== 0 || !str_contains($discoverOutput->fetch(), '"district"')) {
                throw new RuntimeException('Laravel discovery command failed to inspect a real database connection');
            }
        }
        fwrite(STDOUT, "Isolated Composer consumer passed\n");
    } finally {
        if ($original !== false) {
            chdir($original);
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    cleanConsumer($directory);
}
