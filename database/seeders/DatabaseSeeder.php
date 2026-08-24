<?php

namespace Database\Seeders;

use App\Actions\AssignRole;
use App\Enums\ProfileVisibility;
use App\Enums\RoleName;
use App\Enums\VisitFrequency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
    }
}
