<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\BrowserTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature');

pest()->extend(BrowserTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

// Reverb must see fixtures committed by the browser test process.
pest()->extend(BrowserTestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Realtime');

pest()->browser()->timeout(10000);
