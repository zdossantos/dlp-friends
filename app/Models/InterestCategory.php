<?php

namespace App\Models;

use Database\Factories\InterestCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Interest> $interests
 */
#[Fillable(['name', 'sort_order'])]
class InterestCategory extends Model
{
    /** @use HasFactory<InterestCategoryFactory> */
    use HasFactory;

    /** @return HasMany<Interest, $this> */
    public function interests(): HasMany
    {
        return $this->hasMany(Interest::class);
    }
}
