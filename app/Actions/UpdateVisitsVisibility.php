<?php

namespace App\Actions;

use App\Enums\VisitsVisibility;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateVisitsVisibility
{
    public function handle(User $user, VisitsVisibility $visibility, bool $confirmed): void
    {
        DB::transaction(function () use ($user, $visibility, $confirmed): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($visibility === VisitsVisibility::Members && $locked->visits_visibility !== VisitsVisibility::Members && ! $confirmed) {
                throw ValidationException::withMessages(['confirmed' => __('visits.validation.confirmed')]);
            }
            $locked->forceFill(['visits_visibility' => $visibility])->save();
        });
    }
}
