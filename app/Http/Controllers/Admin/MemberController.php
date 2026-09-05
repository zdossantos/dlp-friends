<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SwipeDecision;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = trim($request->string('search')->toString());

        /** @var LengthAwarePaginator<int, User> $members */
        $members = User::query()
            ->select('users.*')
            ->with(['profile.avatar', 'roles'])
            ->withCount([
                'sentSwipes as likes_sent_count' => fn (Builder $query): Builder => $query->where('decision', SwipeDecision::Like),
                'receivedSwipes as likes_received_count' => fn (Builder $query): Builder => $query->where('decision', SwipeDecision::Like),
                'sentSwipes as passes_sent_count' => fn (Builder $query): Builder => $query->where('decision', SwipeDecision::Pass),
                'receivedSwipes as passes_received_count' => fn (Builder $query): Builder => $query->where('decision', SwipeDecision::Pass),
                'lowMatches as low_matches_count',
                'highMatches as high_matches_count',
                'authoredMessages as messages_sent_count',
                'blocksCreated as blocked_count',
                'blocksReceived as blocked_by_count',
            ])
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->where(function (Builder $query) use ($search): void {
                    $query->where('email', 'like', "%{$search}%")
                        ->orWhereHas('profile', fn (Builder $profile): Builder => $profile
                            ->where('display_name', 'like', "%{$search}%"));
                }))
            ->latest('users.created_at')
            ->latest('users.id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (User $member) use ($request): array {
                $isAdmin = $member->hasRole('admin');

                return [
                    'id' => $member->id,
                    'display_name' => $member->profile?->display_name,
                    'email' => $member->email,
                    'status' => $member->status->value,
                    'visibility' => $member->profile?->visibility->value,
                    'created_at' => $member->created_at?->toIso8601String(),
                    'email_verified_at' => $member->email_verified_at?->toIso8601String(),
                    'is_admin' => $isAdmin,
                    'likes_sent_count' => (int) $member->getAttribute('likes_sent_count'),
                    'likes_received_count' => (int) $member->getAttribute('likes_received_count'),
                    'passes_sent_count' => (int) $member->getAttribute('passes_sent_count'),
                    'passes_received_count' => (int) $member->getAttribute('passes_received_count'),
                    'matches_count' => (int) $member->getAttribute('low_matches_count') + (int) $member->getAttribute('high_matches_count'),
                    'messages_sent_count' => (int) $member->getAttribute('messages_sent_count'),
                    'blocked_count' => (int) $member->getAttribute('blocked_count'),
                    'blocked_by_count' => (int) $member->getAttribute('blocked_by_count'),
                    'can_delete' => Gate::forUser($request->user())->allows('delete', $member),
                    'can_start_conversation' => Gate::forUser($request->user())->allows('startConversation', $member),
                ];
            });

        return Inertia::render('Admin/Members/Index', [
            'filters' => ['search' => $search],
            'members' => $members,
            'createdMatch' => $request->session()->pull('admin.members.created_match'),
        ]);
    }
}
