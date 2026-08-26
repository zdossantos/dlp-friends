<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvatarRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Models\Avatar;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class AvatarController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Avatar::class);

        return Inertia::render('Admin/Avatars/Index', [
            'avatars' => Avatar::query()
                ->withCount('profiles')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Avatar $avatar): array => [
                    'id' => $avatar->id,
                    'name' => $avatar->name,
                    'image_url' => route('avatars.image', $avatar),
                    'primary_color' => $avatar->primary_color,
                    'secondary_color' => $avatar->secondary_color,
                    'is_active' => $avatar->is_active,
                    'sort_order' => $avatar->sort_order,
                    'profiles_count' => $avatar->profiles_count,
                ])
                ->all(),
        ]);
    }

    public function store(StoreAvatarRequest $request): RedirectResponse
    {
        $imagePath = $this->storeImage($request->file('image'));

        try {
            DB::transaction(function () use ($request, $imagePath): void {
                $avatars = Avatar::query()->orderBy('id')->lockForUpdate()->get(['sort_order']);

                Avatar::query()->create([
                    'name' => $request->string('name')->toString(),
                    'image_path' => $imagePath,
                    'primary_color' => $request->string('primary_color')->toString(),
                    'secondary_color' => $request->string('secondary_color')->toString(),
                    'is_active' => true,
                    'sort_order' => $avatars->isEmpty() ? 0 : ((int) $avatars->max('sort_order')) + 1,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $this->deleteImage($imagePath);

            throw ValidationException::withMessages(['name' => 'Un avatar porte déjà ce nom.']);
        } catch (Throwable $exception) {
            $this->deleteImage($imagePath);

            throw $exception;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Avatar ajouté.']);

        return back();
    }

    public function update(UpdateAvatarRequest $request, Avatar $avatar): RedirectResponse
    {
        $uploadedImage = $request->file('image');
        $newImagePath = $uploadedImage instanceof UploadedFile
            ? $this->storeImage($uploadedImage)
            : null;

        try {
            $oldImagePath = DB::transaction(function () use ($request, $avatar, $newImagePath): string {
                $lockedAvatar = Avatar::query()
                    ->whereKey($avatar->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $oldImagePath = $lockedAvatar->image_path;

                $lockedAvatar->update([
                    'name' => $request->string('name')->toString(),
                    'image_path' => $newImagePath ?? $oldImagePath,
                    'primary_color' => $request->string('primary_color')->toString(),
                    'secondary_color' => $request->string('secondary_color')->toString(),
                ]);

                return $oldImagePath;
            });
        } catch (UniqueConstraintViolationException) {
            if ($newImagePath !== null) {
                $this->deleteImage($newImagePath);
            }

            throw ValidationException::withMessages(['name' => 'Un avatar porte déjà ce nom.']);
        } catch (Throwable $exception) {
            if ($newImagePath !== null) {
                $this->deleteImage($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath !== null) {
            $this->deleteImage($oldImagePath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Avatar modifié.']);

        return back();
    }

    public function destroy(Avatar $avatar): RedirectResponse
    {
        Gate::authorize('delete', $avatar);

        $imagePath = DB::transaction(function () use ($avatar): string {
            $avatars = Avatar::query()->orderBy('id')->lockForUpdate()->get();
            $lockedAvatar = $avatars->firstWhere('id', $avatar->id);

            abort_unless($lockedAvatar instanceof Avatar, 404);

            if ($lockedAvatar->profiles()->exists()) {
                throw ValidationException::withMessages([
                    'avatar' => 'Cet avatar est encore utilisé par un profil.',
                ]);
            }

            $path = $lockedAvatar->image_path;
            $lockedAvatar->delete();
            $avatars
                ->reject(fn (Avatar $candidate): bool => $candidate->id === $lockedAvatar->id)
                ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                ->values()
                ->each(fn (Avatar $remaining, int $order) => $remaining->update(['sort_order' => $order]));

            return $path;
        });

        $this->deleteImage($imagePath);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Avatar supprimé.']);

        return back();
    }

    private function storeImage(?UploadedFile $image): string
    {
        abort_unless($image instanceof UploadedFile, 422);
        $source = file_get_contents($image->getPathname());
        abort_if($source === false, 500, 'L’image de l’avatar n’a pas pu être lue.');

        $decoded = @imagecreatefromstring($source);
        abort_if($decoded === false, 422, 'L’image de l’avatar est invalide.');

        imagealphablending($decoded, false);
        imagesavealpha($decoded, true);
        ob_start();

        try {
            $mimeType = $image->getMimeType();
            $encoded = match ($mimeType) {
                'image/png' => imagepng($decoded, null, 9),
                'image/webp' => imagewebp($decoded, null, 90),
                default => false,
            };
            $contents = ob_get_clean();
        } finally {
            imagedestroy($decoded);

            if (ob_get_level() > 0 && ! isset($contents)) {
                ob_end_clean();
            }
        }

        abort_if($encoded === false || ! is_string($contents), 500, 'L’image de l’avatar n’a pas pu être réencodée.');

        $extension = $mimeType === 'image/webp' ? 'webp' : 'png';
        $path = 'avatars/'.Str::uuid().'.'.$extension;
        $stored = Storage::put($path, $contents);

        abort_if($stored === false, 500, 'L’image de l’avatar n’a pas pu être enregistrée.');

        return $path;
    }

    private function deleteImage(string $path): void
    {
        if (Storage::delete($path)) {
            return;
        }

        report(new RuntimeException("L’image d’avatar {$path} n’a pas pu être supprimée du stockage."));
    }
}
