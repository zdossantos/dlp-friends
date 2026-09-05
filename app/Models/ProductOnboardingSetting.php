<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $pass_display_name
 * @property string|null $pass_display_name_en
 * @property string $pass_bio
 * @property string|null $pass_bio_en
 * @property string $like_display_name
 * @property string|null $like_display_name_en
 * @property string $like_bio
 * @property string|null $like_bio_en
 */
#[Fillable([
    'id',
    'pass_avatar_id',
    'like_avatar_id',
    'pass_display_name',
    'pass_display_name_en',
    'pass_bio',
    'pass_bio_en',
    'like_display_name',
    'like_display_name_en',
    'like_bio',
    'like_bio_en',
])]
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
