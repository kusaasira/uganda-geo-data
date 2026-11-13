<?php

declare(strict_types=1);

namespace Uganda\Laravel\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Uganda\Laravel\Runtime;
use Uganda\Sync\MappingDefinition;
use Uganda\Sync\SchemaInspector;
use Uganda\Sync\SchemaInstaller;

final class SetupCommand extends Command
{
    protected $signature = 'uganda-geo:setup {--strategy= : managed, hybrid or existing} {--install : Explicitly install Uganda Geo metadata tables}';

    protected $description = 'Interactively prepare a reviewed geography mapping without changing application records';

    public function handle(): int
    {
        $strategy = $this->option('strategy');
        if (!$strategy && $this->input->isInteractive()) {
            $strategy = $this->choice('Select an integration strategy', ['managed', 'hybrid', 'existing'], 'managed');
        }
        $strategy = $strategy ?: 'managed';
        if (!in_array($strategy, ['managed', 'hybrid', 'existing'], true)) {
            throw new RuntimeException('Unsupported integration strategy');
        }

        $pdo = Runtime::pdo();
        $inspector = new SchemaInspector($pdo);
        $config = ['strategy' => $strategy, 'levels' => MappingDefinition::managed()->levels()];
        if ($strategy === 'existing') {
            if (!$this->input->isInteractive()) {
                throw new RuntimeException('Existing-schema setup requires an interactive local console; production uses the saved mapping file');
            }
            $tables = $inspector->tables();
            if ($tables === []) {
                throw new RuntimeException('No usable application tables discovered');
            }
            $config['levels'] = [];
            $parents = ['county' => 'district', 'subcounty' => 'county', 'parish' => 'subcounty', 'village' => 'parish'];
            foreach (['district', 'county', 'subcounty', 'parish', 'village'] as $level) {
                $choice = $this->choice("Existing table for $level", array_merge(['skip'], array_keys($tables)), 'skip');
                if ($choice === 'skip') {
                    continue;
                }
                $columns = $tables[$choice];
                $key = $this->choice("Primary key column for $level", $columns, in_array('id', $columns, true) ? 'id' : $columns[0]);
                $name = $this->choice("Display name column for $level", $columns, in_array('name', $columns, true) ? 'name' : $columns[0]);
                $definition = ['table' => $choice, 'key' => $key, 'name' => $name, 'write_name' => false];
                if (isset($parents[$level], $config['levels'][$parents[$level]])) {
                    $parent = $this->choice("Parent foreign-key column for $level", array_merge(['skip'], $columns), 'skip');
                    if ($parent !== 'skip') {
                        $definition['parent'] = $parent;
                        $definition['parent_level'] = $parents[$level];
                        $definition['write_parent'] = false;
                    }
                }
                $config['levels'][$level] = $definition;
            }
            if ($config['levels'] === []) {
                throw new RuntimeException('No levels were selected');
            }
        }
        if ($strategy === 'hybrid' && $this->input->isInteractive() &&
            $this->confirm('Map existing application tables as read-only identity crosswalk targets?', false)) {
            $tables = $inspector->tables();
            $external = [];
            $parents = ['county' => 'district', 'subcounty' => 'county', 'parish' => 'subcounty', 'village' => 'parish'];
            foreach (['district', 'county', 'subcounty', 'parish', 'village'] as $level) {
                $table = $this->choice("External table for $level", array_merge(['skip'], array_keys($tables)), 'skip');
                if ($table === 'skip') {
                    continue;
                }
                $columns = $tables[$table];
                $key = $this->choice("External unique key for $level", $columns,
                    in_array('id', $columns, true) ? 'id' : $columns[0]);
                $name = $this->choice("External display field for $level", $columns,
                    in_array('name', $columns, true) ? 'name' : $columns[0]);
                $definition = ['table' => $table, 'key' => $key, 'name' => $name];
                $scopeColumn = $this->choice("Optional tenant scope column for $level",
                    array_merge(['skip'], $columns), 'skip');
                if ($scopeColumn !== 'skip') {
                    $definition['scope_column'] = $scopeColumn;
                }
                if (isset($parents[$level], $external[$parents[$level]])) {
                    $parent = $this->choice("External parent foreign key for $level",
                        array_merge(['skip'], $columns), 'skip');
                    if ($parent !== 'skip') {
                        $definition['parent'] = $parent;
                        $definition['parent_level'] = $parents[$level];
                    }
                }
                $external[$level] = $definition;
            }
            if ($external !== []) {
                $config['external_levels'] = $external;
            }
        }
        new MappingDefinition($config);
        $path = config_path('uganda-geo-mapping.json');
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('Mapping file already exists; review and edit it manually instead of overwriting');
        }
        try {
            $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (fwrite($handle, $json) !== strlen($json)) {
                throw new RuntimeException('Incomplete mapping configuration write');
            }
        } finally {
            fclose($handle);
        }
        $this->info('Wrote reviewed mapping template to ' . $path);
        if ($this->option('install')) {
            (new SchemaInstaller($pdo))->install();
            $this->info('Installed Uganda Geo metadata tables');
        } else {
            $this->warn('No database changes made. Use --install only after reviewing the schema.');
        }
        return self::SUCCESS;
    }
}
