<?php

namespace App\Actions;

use App\Models\ApplicationAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class PrepareApplicationAnnouncement
{
    /** @param array<string, mixed> $data */
    public function handle(User $author, array $data): ApplicationAnnouncement
    {
        return DB::transaction(function () use ($author, $data): ApplicationAnnouncement {
            $announcement = ApplicationAnnouncement::query()->create([
                ...Arr::only($data, ['title_fr', 'title_en', 'content_fr', 'content_en', 'destination_url', 'is_release', 'send_push', 'audience']),
                'author_user_id' => $author->id,
            ]);
            User::query()->eligibleForApplicationAnnouncements()
                ->when($data['audience'] === 'selected', fn ($query) => $query->whereKey($data['recipient_ids']))
                ->select('users.id')->chunkById(500, function (Collection $users) use ($announcement): void {
                    $now = now();
                    $announcement->deliveries()->insert($users->map(fn (User $user): array => [
                        'application_announcement_id' => $announcement->id, 'user_id' => $user->id,
                        'status' => 'pending', 'attempts' => 0, 'created_at' => $now, 'updated_at' => $now,
                    ])->all());
                });

            return $announcement;
        });
    }
}
