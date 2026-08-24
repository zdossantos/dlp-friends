<?php

namespace Database\Seeders;

use App\Models\Interest;
use App\Models\InterestCategory;
use App\Models\InterestSetting;
use Illuminate\Database\Seeder;

class InterestCatalogSeeder extends Seeder
{
    /** @var list<string> */
    private const INTERESTS = [
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
    ];

    public function run(): void
    {
        $category = InterestCategory::query()->firstOrCreate(
            ['name' => 'Général'],
            ['sort_order' => 0],
        );

        foreach (self::INTERESTS as $sortOrder => $name) {
            Interest::query()->firstOrCreate(
                ['name' => $name],
                [
                    'interest_category_id' => $category->id,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ],
            );
        }

        InterestSetting::current();
    }
}
