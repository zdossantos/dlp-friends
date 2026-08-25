<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        TestDatabaseGuard::assertSafe($app);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->usesViteAssets()) {
            $this->withoutVite();
        }
    }

    protected function usesViteAssets(): bool
    {
        return false;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
