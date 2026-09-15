<?php

namespace App\Actions;

use App\Enums\RoleAuditAction;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\RoleAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SyncManageableUserRoles
{
    /** @param list<string> $roles */
    public function handle(User $actor, User $target, array $roles): void
    {
        DB::transaction(function () use ($actor, $target, $roles): void {
            $locked = User::query()
                ->lockForUpdate()
                ->with('roles')
                ->findOrFail($target->id);
            $wanted = collect($roles)->map(fn (string $role): RoleName => RoleName::from($role));
            $manageable = [RoleName::User, RoleName::Partner];
            $before = $locked->roles
                ->pluck('name')
                ->filter(fn (RoleName $role): bool => in_array($role, $manageable, true));

            foreach ($manageable as $role) {
                $had = $before->contains($role);
                $has = $wanted->contains($role);

                if ($had === $has) {
                    continue;
                }

                if ($has) {
                    app(AssignRole::class)->handle($locked, $role);
                } else {
                    $roleId = Role::query()->where('name', $role)->value('id');
                    $locked->roles()->detach($roleId);
                }

                RoleAudit::query()->create([
                    'actor_user_id' => $actor->id,
                    'target_user_id' => $locked->id,
                    'role' => $role,
                    'action' => $has ? RoleAuditAction::Assigned : RoleAuditAction::Removed,
                    'expires_at' => now()->addYears(2),
                ]);
            }
        });
    }
}
