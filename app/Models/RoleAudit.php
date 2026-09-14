<?php

namespace App\Models;

use App\Enums\RoleAuditAction;
use App\Enums\RoleName;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $actor_user_id
 * @property int|null $target_user_id
 * @property RoleName $role
 * @property RoleAuditAction $action
 * @property CarbonImmutable|null $expires_at
 * @property-read User|null $actor
 * @property-read User|null $target
 */
#[Fillable(['actor_user_id', 'target_user_id', 'role', 'action', 'expires_at'])]
class RoleAudit extends Model
{
    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => RoleName::class,
            'action' => RoleAuditAction::class,
            'expires_at' => 'immutable_datetime',
        ];
    }
}
