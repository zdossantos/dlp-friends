<?php

namespace App\Models;

use App\Enums\UserDataExportStatus;
use Database\Factories\UserDataExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property UserDataExportStatus $status
 * @property string|null $path
 * @property string|null $failure_reason
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['status', 'path', 'failure_reason', 'expires_at'])]
class UserDataExport extends Model
{
    /** @use HasFactory<UserDataExportFactory> */
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
            'status' => UserDataExportStatus::class,
            'expires_at' => 'datetime',
        ];
    }
}
