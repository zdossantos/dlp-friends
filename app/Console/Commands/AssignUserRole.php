<?php

namespace App\Console\Commands;

use App\Actions\AssignRole;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Command;

class AssignUserRole extends Command
{
    protected $signature = 'user:assign-role {email} {role}';

    protected $description = 'Assign a known application role to a user account';

    public function handle(AssignRole $assignRole): int
    {
        $email = (string) $this->argument('email');
        $roleName = RoleName::tryFrom((string) $this->argument('role'));

        if ($roleName === null) {
            $this->error(__('administration.commands.unknown_role'));

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error(__('administration.commands.user_not_found'));

            return self::FAILURE;
        }

        $attached = $assignRole->handle($user, $roleName);

        $this->info($attached
            ? __('administration.commands.role_assigned')
            : __('administration.commands.role_already_assigned'));

        return self::SUCCESS;
    }
}
