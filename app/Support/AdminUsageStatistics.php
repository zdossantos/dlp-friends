<?php

namespace App\Support;

use App\Enums\ProductOnboardingStatus;
use App\Enums\ProfileVisibility;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Interest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AdminUsageStatistics
{
    /** @return array<string, mixed> */
    public function forPeriod(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $previousFrom = $from->subDays($days);
        $until = $to->addDay();
        $members = $this->members()->select('users.id');
        $matches = DB::table('matches')->whereIn('user_low_id', $members)->whereIn('user_high_id', $members);
        $conversations = DB::table('conversations')->whereIn('match_id', (clone $matches)->select('id'));
        $swipes = DB::table('swipes')->whereIn('actor_user_id', $members)->whereIn('target_user_id', $members);
        $messages = DB::table('messages')->whereIn('author_user_id', $members)->whereIn('conversation_id', (clone $conversations)->select('id'));
        $groups = DB::table('group_chat_messages')->whereIn('author_user_id', $members)->whereNull('system_type');
        $events = DB::table('events')->whereIn('organizer_user_id', $members);
        $visits = DB::table('visits')->whereIn('user_id', $members);
        $trips = DB::table('trips')->whereIn('organizer_user_id', $members);

        $queries = [
            'registrations' => $members->toBase(),
            'likes' => (clone $swipes)->where('decision', 'like'),
            'passes' => (clone $swipes)->where('decision', 'pass'),
            'matches' => $matches,
            'privateMessages' => $messages,
            'groupMessages' => $groups,
            'events' => $events,
            'visits' => $visits,
            'trips' => $trips,
            'eventRegistrations' => DB::table('event_registrations')->whereIn('user_id', $members),
            'tripRegistrations' => DB::table('trip_registrations')->whereIn('user_id', $members),
            'blocks' => DB::table('blocks')->whereIn('blocker_user_id', $members),
            'reports' => DB::table('conversation_reports')->whereIn('reporter_user_id', $members),
        ];

        $daily = [];
        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $daily[$date->toDateString()] = ['date' => $date->toDateString()];
        }

        $metrics = [];
        foreach ($queries as $name => $query) {
            $counts = (clone $query)->reorder()->select([])->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->where('created_at', '>=', $previousFrom)->where('created_at', '<', $until)
                ->groupByRaw('DATE(created_at)')->pluck('total', 'day');

            $metrics[$name] = ['current' => 0, 'previous' => 0];
            foreach ($counts as $date => $count) {
                $metrics[$name][$date >= $from->toDateString() ? 'current' : 'previous'] += (int) $count;
            }
            foreach ($daily as $date => &$point) {
                $point[$name] = (int) ($counts[$date] ?? 0);
            }
            unset($point);
        }

        $actions = null;
        foreach ([[$swipes, 'actor_user_id'], [$messages, 'author_user_id'], [$groups, 'author_user_id'], [$events, 'organizer_user_id'], [$visits, 'user_id'], [$trips, 'organizer_user_id']] as [$query, $actor]) {
            $source = (clone $query)->selectRaw("{$actor} as user_id, created_at")
                ->where('created_at', '>=', $previousFrom)->where('created_at', '<', $until);
            $actions = $actions === null ? $source : $actions->unionAll($source);
        }

        $contributions = DB::query()->fromSub($actions, 'actions');
        $counts = (clone $contributions)->selectRaw('DATE(created_at) as day, COUNT(DISTINCT user_id) as total')
            ->groupByRaw('DATE(created_at)')->pluck('total', 'day');
        foreach ($daily as $date => &$point) {
            $point['contributors'] = (int) ($counts[$date] ?? 0);
        }
        unset($point);

        $metrics['contributors'] = [
            'current' => (clone $contributions)->where('created_at', '>=', $from)->distinct()->count('user_id'),
            'previous' => (clone $contributions)->where('created_at', '<', $from)->distinct()->count('user_id'),
        ];

        $cohort = $this->members()->where('users.created_at', '>=', $from)->where('users.created_at', '<', $until);
        $registered = (clone $cohort)->count();
        $cohort->whereNotNull('email_verified_at');
        $verified = (clone $cohort)->count();
        $cohort->whereHas('profile', fn (EloquentBuilder $profile) => $profile->whereNotNull('onboarding_completed_at')
            ->whereHas('avatar', fn (EloquentBuilder $avatar) => $avatar->where('is_active', true)));
        $profileCompleted = (clone $cohort)->count();
        $tutorialCompleted = $cohort->whereHas('productOnboarding', fn (EloquentBuilder $onboarding) => $onboarding->where('status', ProductOnboardingStatus::Completed))->count();

        $cohortMatches = (clone $matches)->where('created_at', '>=', $from)->where('created_at', '<', $until);
        $withMessages = DB::table('conversations')->whereIn('match_id', $cohortMatches->select('id'))
            ->whereExists(fn (Builder $query) => $query->selectRaw('1')->from('messages')->whereColumn('messages.conversation_id', 'conversations.id'))->count();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'previousFrom' => $previousFrom->toDateString(), 'previousTo' => $from->subDay()->toDateString(), 'days' => $days],
            'daily' => array_values($daily),
            'metrics' => $metrics,
            'activation' => compact('registered', 'verified', 'profileCompleted', 'tutorialCompleted'),
            'conversations' => ['total' => $metrics['matches']['current'], 'withMessages' => $withMessages],
            'community' => [
                'members' => $this->members()->count(),
                'enabledAccounts' => $this->members()->where('status', UserStatus::Active)->count(),
                'bannedAccounts' => $this->members()->where('status', UserStatus::Banned)->count(),
                'pendingDeletion' => $this->members()->where('status', UserStatus::PendingDeletion)->count(),
                'hiddenProfiles' => $this->members()->whereHas('profile', fn (EloquentBuilder $profile) => $profile->where('visibility', ProfileVisibility::Hidden))->count(),
            ],
            'interests' => Interest::query()->select(['id', 'name', 'name_en'])->where('is_active', true)
                ->withCount(['profiles as selections' => fn (EloquentBuilder $profiles) => $profiles->where('interest_profile.is_selected', true)->whereIn('user_id', $members)])
                ->having('selections', '>', 0)->orderByDesc('selections')->orderBy('id')->limit(8)->get()
                ->map(fn (Interest $interest): array => ['name' => $interest->display_name, 'count' => (int) $interest->getAttribute('selections')])->all(),
        ];
    }

    /** @return EloquentBuilder<User> */
    private function members(): EloquentBuilder
    {
        return User::query()->whereHas('roles', fn (EloquentBuilder $roles) => $roles->where('name', RoleName::User))
            ->whereDoesntHave('roles', fn (EloquentBuilder $roles) => $roles->where('name', RoleName::Admin));
    }
}
