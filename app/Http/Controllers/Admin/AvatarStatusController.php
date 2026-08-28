<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAvatarStatusRequest;
use App\Models\Avatar;
use App\Models\ProductOnboardingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AvatarStatusController extends Controller
{
    public function __invoke(UpdateAvatarStatusRequest $request, Avatar $avatar): RedirectResponse
    {
        $isActive = $request->boolean('is_active');
        DB::transaction(function () use ($avatar, $isActive): void {
            $lockedAvatar = Avatar::query()->whereKey($avatar->id)->lockForUpdate()->firstOrFail();

            if (! $isActive && $this->isUsedByOnboarding($lockedAvatar)) {
                throw ValidationException::withMessages([
                    'avatar' => __('Cet avatar est utilisé par le tutoriel. Remplacez-le dans la configuration avant de continuer.'),
                ]);
            }

            $lockedAvatar->update(['is_active' => $isActive]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isActive ? __('Avatar réactivé.') : __('Avatar archivé.'),
        ]);

        return back();
    }

    private function isUsedByOnboarding(Avatar $avatar): bool
    {
        return ProductOnboardingSetting::query()
            ->where(fn ($query) => $query
                ->where('pass_avatar_id', $avatar->id)
                ->orWhere('like_avatar_id', $avatar->id))
            ->lockForUpdate()
            ->exists();
    }
}
