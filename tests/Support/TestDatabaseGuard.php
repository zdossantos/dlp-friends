<?php

namespace Tests\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use RuntimeException;
use Throwable;

final class TestDatabaseGuard
{
    public static function assertSafe(Application $app): string
    {
        if ($app->configurationIsCached()) {
            throw new RuntimeException(
                'Backend tests refuse cached Laravel configuration. Run [php artisan config:clear] first.',
            );
        }

        try {
            $connection = $app->make(DatabaseManager::class)->connection();
            $configuredDriver = $connection->getDriverName();
            $pdoDriver = (string) $connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Unable to connect to the isolated MySQL test database: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        if ($configuredDriver !== 'mysql' || $pdoDriver !== 'mysql') {
            throw new RuntimeException(
                "Backend tests require MySQL; configured driver [{$configuredDriver}], PDO driver [{$pdoDriver}].",
            );
        }

        $expectedDatabase = (string) $app->make('config')->get('database.test_database');

        try {
            $actualDatabase = (string) $connection->scalar('SELECT DATABASE()');
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Unable to verify the isolated MySQL test database: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        if ($actualDatabase !== $expectedDatabase) {
            throw new RuntimeException(
                "Backend tests require the isolated database [{$expectedDatabase}]; connected database [{$actualDatabase}].",
            );
        }

        return $actualDatabase;
    }
}
