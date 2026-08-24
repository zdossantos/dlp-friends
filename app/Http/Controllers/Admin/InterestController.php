<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInterestRequest;
use App\Http\Requests\UpdateInterestRequest;
use App\Models\Interest;
use App\Models\InterestCategory;
use App\Models\InterestSetting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InterestController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Interest::class);

        return Inertia::render('Admin/Interests/Index', [
            'interests' => Interest::query()
                ->withCount('profiles')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'is_active', 'sort_order'])
                ->map(fn (Interest $interest): array => [
                    'id' => $interest->id,
                    'name' => $interest->name,
                    'is_active' => $interest->is_active,
                    'sort_order' => $interest->sort_order,
                    'profiles_count' => $interest->profiles_count,
                ])
                ->all(),
            'setting' => [
                'max_selections' => InterestSetting::current()->max_selections,
            ],
        ]);
    }

    public function store(StoreInterestRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request): void {
                $category = InterestCategory::query()
                    ->where('name', 'Général')
                    ->firstOrFail();
                $lockedInterests = Interest::query()
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id', 'sort_order']);

                Interest::query()->create([
                    'interest_category_id' => $category->id,
                    'name' => $request->string('name')->toString(),
                    'is_active' => true,
                    'sort_order' => $lockedInterests->isEmpty()
                        ? 0
                        : ((int) $lockedInterests->max('sort_order')) + 1,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'Un intérêt porte déjà ce nom.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Intérêt ajouté.',
        ]);

        return back();
    }

    public function update(UpdateInterestRequest $request, Interest $interest): RedirectResponse
    {
        try {
            $interest->update([
                'name' => $request->string('name')->toString(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'Un intérêt porte déjà ce nom.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Intérêt modifié.',
        ]);

        return back();
    }

    public function destroy(Interest $interest): RedirectResponse
    {
        Gate::authorize('delete', $interest);

        DB::transaction(function () use ($interest): void {
            $lockedInterests = Interest::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $lockedInterest = $lockedInterests->firstWhere('id', $interest->id);

            abort_unless($lockedInterest instanceof Interest, 404);

            if ($lockedInterest->is_active && $lockedInterest->profiles()->exists()) {
                throw ValidationException::withMessages([
                    'interest' => 'Cet intérêt a déjà été utilisé. Archivez-le avant de le supprimer.',
                ]);
            }

            $lockedInterest->delete();
            $lockedInterests
                ->reject(fn (Interest $candidate): bool => $candidate->id === $lockedInterest->id)
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->values()
                ->each(function (Interest $remainingInterest, int $sortOrder): void {
                    $remainingInterest->update(['sort_order' => $sortOrder]);
                });
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Intérêt supprimé.',
        ]);

        return back();
    }
}
