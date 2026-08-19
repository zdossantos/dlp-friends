<?php

namespace App\Actions;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;

class AssignRole
{
    public function handle(User $user, RoleName $roleName): bool
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $changes = $user->roles()->syncWithoutDetaching($role);

        $user->unsetRelation('roles');

        return $changes['attached'] !== [];
    }
}
