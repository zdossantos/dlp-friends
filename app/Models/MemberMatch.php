<?php

namespace App\Models;

use Database\Factories\MemberMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_low_id
 * @property int $user_high_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $lowUser
 * @property-read User $highUser
 * @property-read Conversation|null $conversation
 */
#[Fillable(['user_low_id', 'user_high_id'])]
class MemberMatch extends Model
{
    /** @use HasFactory<MemberMatchFactory> */
    use HasFactory;

    protected $table = 'matches';

    /** @return BelongsTo<User, $this> */
    public function lowUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_low_id');
    }

    /** @return BelongsTo<User, $this> */
    public function highUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_high_id');
    }

    /** @return HasOne<Conversation, $this> */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'match_id');
    }
}
