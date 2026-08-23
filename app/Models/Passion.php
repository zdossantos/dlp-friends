<?php

namespace App\Models;

use Database\Factories\PassionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $passion_category_id
 * @property string $name
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PassionCategory $category
 * @property-read Collection<int, Profile> $profiles
 */
#[Fillable(['passion_category_id', 'name', 'is_active', 'sort_order'])]
class Passion extends Model
{
    /** @use HasFactory<PassionFactory> */
    use HasFactory;

    /** @return BelongsTo<PassionCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PassionCategory::class, 'passion_category_id');
    }

    /** @return BelongsToMany<Profile, $this> */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class);
    }
}
