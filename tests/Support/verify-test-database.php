<?php

use Illuminate\Contracts\Console\Kernel;
use Tests\Support\TestDatabaseGuard;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $database = TestDatabaseGuard::assertSafe($app);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}

fwrite(STDOUT, "Test database: mysql/{$database}\n");
