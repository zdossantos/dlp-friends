<?php

namespace App\Models;

use Database\Factories\InterestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $interest_category_id
 * @property string $name
 * @property string|null $name_en
 * @property-read string $display_name
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InterestCategory $category
 * @property-read Collection<int, Profile> $profiles
 */
#[Fillable(['interest_category_id', 'name', 'name_en', 'is_active', 'sort_order'])]
class Interest extends Model
{
    /** @use HasFactory<InterestFactory> */
    use HasFactory;

    /** @return BelongsTo<InterestCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InterestCategory::class, 'interest_category_id');
    }

    /** @return BelongsToMany<Profile, $this> */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class)->withPivot('is_selected');
    }

    /** @return Attribute<covariant string, never> */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => app()->getLocale() === 'en' && $this->name_en !== null
            ? $this->name_en
            : $this->name);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
