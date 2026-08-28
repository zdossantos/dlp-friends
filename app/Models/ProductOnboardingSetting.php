<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pass_avatar_id', 'like_avatar_id'])]
class ProductOnboardingSetting extends Model
{
    public const int SINGLETON_ID = 1;

    /** @return BelongsTo<Avatar, $this> */
    public function passAvatar(): BelongsTo
    {
        return $this->belongsTo(Avatar::class, 'pass_avatar_id');
    }

    /** @return BelongsTo<Avatar, $this> */
    public function likeAvatar(): BelongsTo
    {
        return $this->belongsTo(Avatar::class, 'like_avatar_id');
    }

    public static function current(): ?self
    {
        return self::query()
            ->with(['passAvatar', 'likeAvatar'])
            ->find(self::SINGLETON_ID);
    }
}

