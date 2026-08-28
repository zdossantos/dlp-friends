<?php

namespace App\Models;

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProductOnboardingStep;
use Database\Factories\ProductOnboardingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'status', 'step'])]
class ProductOnboarding extends Model
{
    /** @use HasFactory<ProductOnboardingFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProductOnboardingStatus::class,
            'step' => ProductOnboardingStep::class,
        ];
    }
}

