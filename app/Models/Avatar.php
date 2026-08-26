<?php

namespace App\Models;

use Database\Factories\AvatarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $image_path
 * @property string $primary_color
 * @property string $secondary_color
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Profile> $profiles
 *
 * @method static Builder<static> active()
 */
#[Fillable(['name', 'image_path', 'primary_color', 'secondary_color', 'is_active', 'sort_order'])]
class Avatar extends Model
{
    /** @use HasFactory<AvatarFactory> */
    use HasFactory;

    /** @param Builder<Avatar> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return HasMany<Profile, $this> */
    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
