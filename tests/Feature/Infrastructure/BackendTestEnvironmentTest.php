<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

class BackendTestEnvironmentTest extends TestCase
{
    public function test_backend_tests_run_on_mysql(): void
    {
        $connection = DB::connection();

        $this->assertSame('mysql', $connection->getDriverName());
        $this->assertSame('mysql', $connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    public function test_database_guard_rejects_a_database_not_reserved_for_tests(): void
    {
        config(['database.test_database' => 'another_test_database']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Backend tests require the isolated database [another_test_database]; connected database [dlp_friends_test].',
        );

        TestDatabaseGuard::assertSafe($this->app);
    }
}
