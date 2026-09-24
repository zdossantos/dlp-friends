<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $enabled
 * @property-read User $user
 */
#[Fillable(['user_id', 'enabled'])]
class PartnerNotificationPreference extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function enabledFor(User $user): bool
    {
        return self::query()
            ->whereBelongsTo($user)
            ->where('enabled', true)
            ->exists();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
