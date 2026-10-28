<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;

// Invoked only by the root-owned relay, inside the selected application image.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if (app()->isDownForMaintenance() || ! in_array(app()->environment(), ['production', 'beta'], true)) {
    exit(78);
}

if (($argv[1] ?? '') === 'migrate') {
    DB::statement('SET SESSION lock_wait_timeout = 5');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 5');
    exit($kernel->call('migrate', ['--force' => true, '--no-interaction' => true]));
}

$migrator = $app->make(Migrator::class);
if (! $migrator->repositoryExists()) {
    exit(78);
}
$files = $migrator->getMigrationFiles([...$migrator->paths(), database_path('migrations')]);
$migrations = [];
foreach ($files as $name => $path) {
    $migrations[$name] = hash_file('sha256', $path);
}
$policyPath = 'deployment/rolling-migrations.json';
$policy = is_file($policyPath) ? json_decode(file_get_contents($policyPath), true, flags: JSON_THROW_ON_ERROR) : [];

// Compare resolved configuration without disclosing its secrets to the caller.
$criticalConfig = [];
foreach (['app.key', 'app.url', 'database', 'session', 'cache', 'queue', 'filesystems', 'broadcasting', 'services', 'mail'] as $key) {
    $criticalConfig[$key] = config($key);
}
echo json_encode([
    'identity' => [
        'environment' => app()->environment(),
        'database' => config('database.connections.mysql.database'),
        'server' => DB::selectOne('SELECT @@server_uuid AS uuid')->uuid,
        'configuration' => hash('sha256', json_encode($criticalConfig, JSON_THROW_ON_ERROR)),
    ],
    'migrations' => $migrations,
    'ran' => $migrator->getRepository()->getRan(),
    'policy' => $policy,
], JSON_THROW_ON_ERROR);
