<?php

namespace Database\Seeders;

use App\Actions\AssignRole;
use App\Enums\ProfileVisibility;
use App\Enums\RoleName;
use App\Enums\VisitFrequency;
use App\Models\Avatar;
use App\Models\Interest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(AssignRole $assignRole): void
    {
        $this->call(InterestCatalogSeeder::class);

        foreach (RoleName::cases() as $roleName) {
            Role::query()->firstOrCreate(['name' => $roleName]);
        }

        $user = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'birth_date' => today()->subYears(25),
                'password' => 'password',
            ],
        );

        $user->forceFill(['email_verified_at' => now()])->save();

        $user->profile()->updateOrCreate([], [
            'display_name' => 'Test User',
            'bio' => 'Compte administrateur local de démonstration.',
            'visit_frequency' => VisitFrequency::Often,
            'visibility' => ProfileVisibility::Visible,
            'onboarding_completed_at' => now(),
        ]);

        $assignRole->handle($user, RoleName::User);
        $assignRole->handle($user, RoleName::Admin);

        $avatarImage = file_get_contents(public_path('apple-touch-icon.png'));

        if ($avatarImage === false) {
            throw new \RuntimeException('The local demo avatar image is missing.');
        }

        $avatarPath = 'avatars/demo-member.png';
        Storage::put($avatarPath, $avatarImage);
        $avatarColors = [
            ['#7138B6', '#E879B9'],
            ['#2563EB', '#67E8F9'],
            ['#C2410C', '#FACC15'],
            ['#047857', '#6EE7B7'],
            ['#BE123C', '#FDA4AF'],
            ['#4338CA', '#C4B5FD'],
        ];
        $avatars = collect($avatarColors)->map(
            fn (array $colors, int $index): Avatar => Avatar::query()->updateOrCreate(
                ['name' => 'Avatar démo '.($index + 1)],
                [
                    'image_path' => $avatarPath,
                    'primary_color' => $colors[0],
                    'secondary_color' => $colors[1],
                    'is_active' => true,
                    'sort_order' => 100 + $index,
                ],
            ),
        )->values();
        $interestIds = Interest::query()->where('is_active', true)->orderBy('sort_order')->pluck('id')->values();
        $frequencies = VisitFrequency::cases();

        foreach (range(1, 24) as $index) {
            $demoMember = User::query()->updateOrCreate(
                ['email' => "user{$index}@example.com"],
                [
                    'birth_date' => today()->subYears(20 + ($index % 25)),
                    'password' => 'password',
                ],
            );
            $demoMember->forceFill(['email_verified_at' => now()])->save();
            $profile = $demoMember->profile()->updateOrCreate([], [
                'avatar_id' => $avatars[($index - 1) % $avatars->count()]->id,
                'display_name' => 'Membre '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'bio' => "Fan de parcs et de sorties amicales — profil de démonstration {$index}.",
                'visit_frequency' => $frequencies[($index - 1) % count($frequencies)],
                'visibility' => ProfileVisibility::Visible,
                'onboarding_completed_at' => now(),
            ]);
            $selectedInterestIds = collect(range(0, 4))
                ->map(fn (int $offset): int => $interestIds[($index + $offset - 1) % $interestIds->count()])
                ->all();

            $profile->interestHistory()->syncWithPivotValues($selectedInterestIds, ['is_selected' => true]);
            $assignRole->handle($demoMember, RoleName::User);
        }
    }
}
