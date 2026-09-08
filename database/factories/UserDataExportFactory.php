<?php

namespace Database\Factories;

use App\Enums\UserDataExportStatus;
use App\Models\User;
use App\Models\UserDataExport;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserDataExport> */
class UserDataExportFactory extends Factory
{
    protected $model = UserDataExport::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => UserDataExportStatus::Pending,
            'path' => null,
            'failure_reason' => null,
            'expires_at' => null,
        ];
    }
}
