<?php

namespace App\Actions;

use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SavePartnerProfileDraft
{
    public function __construct(private readonly TransformPartnerImage $transformImage) {}

    /** @param array{name_fr: string, name_en: string, description_fr: string, description_en: string} $data */
    public function handle(User $partner, array $data, ?UploadedFile $image): PartnerProfileRevision
    {
        $newImagePath = $image === null ? null : $this->transformImage->handle($image);
        $oldImagePath = null;

        try {
            $revision = DB::transaction(function () use ($partner, $data, $newImagePath, &$oldImagePath): PartnerProfileRevision {
                User::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();

                $profile = PartnerProfile::query()
                    ->where('user_id', $partner->id)
                    ->lockForUpdate()
                    ->first();

                if ($profile === null) {
                    $profile = PartnerProfile::query()->create(['user_id' => $partner->id]);
                }

                $draft = $profile->revisions()
                    ->where('status', PartnerRevisionStatus::Draft)
                    ->lockForUpdate()
                    ->first();
                if ($draft !== null && $newImagePath !== null) {
                    $oldImagePath = $draft->image_path;
                }

                $imagePath = $newImagePath
                    ?? $draft->image_path
                    ?? $profile->revisions()->latest('id')->value('image_path');
                $attributes = [
                    ...$data,
                    'image_path' => $imagePath,
                    'status' => PartnerRevisionStatus::Draft,
                    'submitted_at' => null,
                    'decided_at' => null,
                    'decided_by' => null,
                    'rejection_reason' => null,
                    'draft_key' => 1,
                ];

                if ($draft === null) {
                    return $profile->revisions()->create($attributes);
                }

                $draft->update($attributes);

                return $draft->refresh();
            });
        } catch (Throwable $exception) {
            if ($newImagePath !== null) {
                Storage::delete($newImagePath);
            }

            throw $exception;
        }

        if ($oldImagePath !== null
            && $oldImagePath !== $newImagePath
            && ! PartnerProfileRevision::query()->where('image_path', $oldImagePath)->exists()) {
            Storage::delete($oldImagePath);
        }

        return $revision;
    }
}
