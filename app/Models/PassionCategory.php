<?php

namespace App\Models;

use Database\Factories\PassionCategoryFactory;
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
 * @property-read Collection<int, Passion> $passions
 */
#[Fillable(['name', 'sort_order'])]
class PassionCategory extends Model
{
    /** @use HasFactory<PassionCategoryFactory> */
    use HasFactory;

    /** @return HasMany<Passion, $this> */
    public function passions(): HasMany
    {
        return $this->hasMany(Passion::class);
    }
}
