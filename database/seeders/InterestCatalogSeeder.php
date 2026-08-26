<?php

namespace Database\Seeders;

use App\Models\Interest;
use App\Models\InterestCategory;
use App\Models\InterestSetting;
use Illuminate\Database\Seeder;

class InterestCatalogSeeder extends Seeder
{
    /** @var array<string, string> */
    private const INTERESTS = [
        'Chill' => 'Relaxing',
        'Attractions à sensations' => 'Thrill rides',
        'Attractions calmes' => 'Gentle rides',
        'Collection / merch' => 'Collecting / merch',
        'Pins' => 'Pins',
        'Rencontres personnages' => 'Character encounters',
        'Spectacles' => 'Shows',
        'Food' => 'Food',
        'Secrets / anecdotes' => 'Secrets / stories',
        'Événements' => 'Events',
    ];

    public function run(): void
    {
        $category = InterestCategory::query()->firstOrCreate(
            ['name' => 'Général'],
            ['sort_order' => 0],
        );

        foreach (array_keys(self::INTERESTS) as $sortOrder => $name) {
            $nameEn = self::INTERESTS[$name];

            Interest::query()->firstOrCreate(
                ['name' => $name],
                [
                    'interest_category_id' => $category->id,
                    'name_en' => $nameEn,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ],
            );
        }

        InterestSetting::current();
    }
}
