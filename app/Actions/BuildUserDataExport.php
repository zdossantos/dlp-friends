<?php

namespace App\Actions;

use App\Models\Conversation;
use App\Models\Interest;
use App\Models\MemberMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class BuildUserDataExport
{
    /** @return array<string, mixed> */
    public function handle(User $user): array
    {
        $user->loadMissing('profile.avatar', 'profile.interestHistory');

        $matches = MemberMatch::query()
            ->where(fn (Builder $query) => $query
                ->where('user_low_id', $user->id)
                ->orWhere('user_high_id', $user->id))
            ->with('lowUser.profile', 'highUser.profile')
            ->orderBy('id')
            ->get();

        $conversations = Conversation::query()
            ->forMember($user)
            ->withVisibleParticipant($user)
            ->with(['messages' => fn ($query) => $query
                ->where('author_user_id', $user->id)
                ->orderBy('id')])
            ->orderBy('id')
            ->get();

        $messages = [];
        foreach ($conversations as $conversation) {
            foreach ($conversation->messages as $message) {
                $messages[] = [
                    'conversation_id' => $conversation->id,
                    'author' => $message->author_user_id === $user->id ? 'self' : 'other',
                    'content' => $message->content,
                    'read_at' => $message->read_at?->toIso8601String(),
                    'created_at' => $message->created_at?->toIso8601String(),
                    'updated_at' => $message->updated_at?->toIso8601String(),
                ];
            }
        }

        return [
            'format_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'account' => [
                'email' => $user->email,
                'locale' => $user->locale,
                'birth_date' => $user->birth_date?->toDateString(),
                'status' => $user->status->value,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'profile' => $user->profile === null ? null : [
                'display_name' => $user->profile->display_name,
                'bio' => $user->profile->bio,
                'visit_frequency' => $user->profile->visit_frequency?->value,
                'visibility' => $user->profile->visibility->value,
                'avatar' => $user->profile->avatar === null ? null : [
                    'id' => $user->profile->avatar->id,
                    'name' => $user->profile->avatar->name,
                ],
                'created_at' => $user->profile->created_at?->toIso8601String(),
                'updated_at' => $user->profile->updated_at?->toIso8601String(),
            ],
            'interests' => $user->profile?->interestHistory
                ->sortBy('id')
                ->values()
                ->map(fn (Interest $interest): array => [
                    'id' => $interest->id,
                    'name_fr' => $interest->name,
                    'name_en' => $interest->name_en,
                    'is_active' => $interest->is_active,
                    'is_selected' => (bool) data_get($interest, 'pivot.is_selected'),
                ])->all() ?? [],
            'matches' => $matches->map(function (MemberMatch $match) use ($user): array {
                $other = $match->user_low_id === $user->id ? $match->highUser : $match->lowUser;

                return [
                    'id' => $match->id,
                    'other_member' => [
                        'id' => $other->id,
                        'display_name' => $other->profile?->display_name,
                    ],
                    'created_at' => $match->created_at?->toIso8601String(),
                    'updated_at' => $match->updated_at?->toIso8601String(),
                ];
            })->all(),
            'messages' => $messages,
        ];
    }
}
