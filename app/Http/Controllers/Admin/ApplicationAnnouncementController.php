<?php

namespace App\Http\Controllers\Admin;

use App\Actions\PrepareApplicationAnnouncement;
use App\Actions\SendApplicationAnnouncement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PrepareApplicationAnnouncementRequest;
use App\Models\ApplicationAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ApplicationAnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ApplicationAnnouncement::class);
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '';

        return Inertia::render('Admin/ApplicationAnnouncements/Index', [
            'announcements' => ApplicationAnnouncement::query()->withCount('deliveries')->latest('id')->paginate(20),
            'search' => $search,
            'recipients' => User::query()->eligibleForApplicationAnnouncements()->with('profile')
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('email', 'like', '%'.$search.'%')
                    ->orWhereHas('profile', fn (Builder $profile) => $profile->where('display_name', 'like', '%'.$search.'%'))))
                ->orderBy('id')->limit(50)->get()->map(fn (User $user): array => [
                    'id' => $user->id, 'label' => $user->profile->display_name ?? $user->email,
                    'email' => $user->email,
                ]),
        ]);
    }

    public function store(PrepareApplicationAnnouncementRequest $request, PrepareApplicationAnnouncement $prepare): RedirectResponse
    {
        $announcement = $prepare->handle($request->user(), $request->validated());

        return to_route('admin.application-announcements.show', $announcement);
    }

    public function show(ApplicationAnnouncement $announcement): Response
    {
        Gate::authorize('viewAny', ApplicationAnnouncement::class);
        $counts = $announcement->deliveries()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Admin/ApplicationAnnouncements/Show', [
            'announcement' => [
                ...$announcement->only(['id', 'title_fr', 'title_en', 'content_fr', 'content_en', 'destination_url', 'is_release', 'send_push', 'audience']),
                'sent_at' => $announcement->sent_at?->toIso8601String(),
                'counts' => collect(['pending', 'delivered', 'skipped', 'failed'])->mapWithKeys(fn (string $status): array => [$status => (int) ($counts[$status] ?? 0)]),
            ],
            'recipients' => $announcement->deliveries()->with('user.profile')->orderBy('id')->paginate(50)
                ->through(fn ($delivery): array => [
                    'id' => $delivery->id, 'label' => $delivery->user->profile->display_name ?? $delivery->user->email,
                    'email' => $delivery->user->email, 'status' => $delivery->status,
                ]),
        ]);
    }

    public function send(ApplicationAnnouncement $announcement, SendApplicationAnnouncement $send): RedirectResponse
    {
        Gate::authorize('create', ApplicationAnnouncement::class);
        $send->handle($announcement);

        return to_route('admin.application-announcements.show', $announcement);
    }

    public function retry(ApplicationAnnouncement $announcement, SendApplicationAnnouncement $send): RedirectResponse
    {
        Gate::authorize('create', ApplicationAnnouncement::class);
        abort_if($announcement->sent_at === null, 409);
        $send->handle($announcement);

        return to_route('admin.application-announcements.show', $announcement);
    }
}
