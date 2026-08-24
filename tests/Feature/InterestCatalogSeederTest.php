<?php

use App\Models\Interest;
use App\Models\InterestSetting;
use Database\Seeders\InterestCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it seeds the default limit and ordered interests idempotently', function (): void {
    $this->seed(InterestCatalogSeeder::class);
    $this->seed(InterestCatalogSeeder::class);

    expect(InterestSetting::current()->max_selections)->toBe(5)
        ->and(Interest::query()->orderBy('sort_order')->pluck('name')->all())->toBe([
            'Chill',
            'Attractions à sensations',
            'Attractions calmes',
            'Collection / merch',
            'Pins',
            'Rencontres personnages',
            'Spectacles',
            'Food',
            'Secrets / anecdotes',
            'Événements',
        ]);

    $this->assertDatabaseCount('interest_settings', 1);
    $this->assertDatabaseCount('interests', 10);
});

test('reseeding does not reactivate or reorder existing interests', function (): void {
    $this->seed(InterestCatalogSeeder::class);
    Interest::query()->where('name', 'Chill')->update([
        'is_active' => false,
        'sort_order' => 42,
    ]);

    $this->seed(InterestCatalogSeeder::class);

    $this->assertDatabaseHas('interests', [
        'name' => 'Chill', 'is_active' => false, 'sort_order' => 42,
    ]);
});
