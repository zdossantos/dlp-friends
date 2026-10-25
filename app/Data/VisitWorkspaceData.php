<?php

namespace App\Data;

use App\Models\Profile;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class VisitWorkspaceData
{
    /**
     * @param  array{month:string,day:string,view:string,audience:string,search:string,member:int|null}  $filters
     * @return array<string,mixed>
     */
    public function build(User $viewer, array $filters): array
    {
        $month = CarbonImmutable::parse($filters['month'].'-01', 'Europe/Paris');
        $today = CarbonImmutable::today('Europe/Paris');
        $own = $viewer->visits()->where('ends_on', '>=', $today->toDateString())->orderBy('starts_on')->orderBy('id')->get();
        $previews = $this->calendarPreviews($viewer, $filters, $month->max($today), $month->endOfMonth());
        $days = [];
        for ($date = $month; $date->month === $month->month; $date = $date->addDay()) {
            if ($date->lt($today)) {
                continue;
            }
            $count = $previews[$date->toDateString()]['count'] ?? 0;
            $preview = $previews[$date->toDateString()]['preview'] ?? [];
            $days[] = ['date' => $date->toDateString(), 'isToday' => $date->isSameDay($today), 'ownVisitIds' => $own->filter(fn (Visit $visit): bool => $visit->starts_on->toDateString() <= $date->toDateString() && $visit->ends_on->toDateString() >= $date->toDateString())->pluck('id')->values()->all(), 'otherCount' => $count, 'preview' => $preview];
        }

        return ['today' => $today->toDateString(), 'filters' => $filters, 'visibility' => $viewer->visits_visibility->value, 'ownVisits' => $own->map(fn (Visit $visit): array => ['id' => $visit->id, 'starts_on' => $visit->starts_on->toDateString(), 'ends_on' => $visit->ends_on->toDateString()])->all(), 'days' => $days, 'closeHref' => route('visits.index', $filters, false), 'panel' => null];
    }

    /**
     * @param  array{month:string,day:string,view:string,audience:string,search:string,member:int|null}  $filters
     * @return array<string,mixed>|null
     */
    public function profile(User $viewer, array $filters, ?int $id, ?string $day): ?array
    {
        if ($id === null) {
            return null;
        }

        $member = $this->attendees($viewer, $filters, CarbonImmutable::parse($day ?? $filters['day'], 'Europe/Paris'))->whereKey($id)->firstOrFail();
        abort_unless($member->profile !== null && Gate::forUser($viewer)->allows('viewPublic', $member->profile), 404);

        return ['isBlocked' => false] + PublicMemberData::from($viewer, $member);
    }

    /**
     * @param  array{month:string,day:string,view:string,audience:string,search:string,member:int|null}  $filters
     * @return Builder<User>
     */
    public function attendees(User $viewer, array $filters, CarbonImmutable $date): Builder
    {
        $visits = Visit::query()->visibleTo($viewer)->overlapping($date, $date)->select('user_id');
        $query = $this->people($viewer, $filters, $visits);
        if ($date->startOfDay()->lt(CarbonImmutable::today('Europe/Paris'))) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * @param  array{month:string,day:string,view:string,audience:string,search:string,member:int|null}  $filters
     * @param  Builder<Visit>  $visits
     * @return Builder<User>
     */
    private function people(User $viewer, array $filters, Builder $visits): Builder
    {
        $query = User::query()->where('users.id', '!=', $viewer->id)->whereIn('users.id', $visits)
            ->with('profile.avatar')
            ->withExists(['lowMatches as visit_low_match' => fn (Builder $match) => $match->where('user_high_id', $viewer->id), 'highMatches as visit_high_match' => fn (Builder $match) => $match->where('user_low_id', $viewer->id)])
            ->orderBy(Profile::query()->select('display_name')->whereColumn('profiles.user_id', 'users.id'))->orderBy('users.id');
        if ($filters['audience'] === 'matches') {
            $query->where(fn (Builder $paired) => $paired->whereHas('lowMatches', fn (Builder $m) => $m->where('user_high_id', $viewer->id))->orWhereHas('highMatches', fn (Builder $m) => $m->where('user_low_id', $viewer->id)));
        }
        if ($filters['search'] !== '') {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']);
            $query->whereHas('profile', fn (Builder $p) => $p->whereRaw("display_name LIKE ? ESCAPE '!'", ['%'.$literal.'%']));
        }
        if ($filters['member'] !== null) {
            $query->whereKey($filters['member']);
        }

        return $query;
    }

    /**
     * @param  array{month:string,day:string,view:string,audience:string,search:string,member:int|null}  $filters
     * @return array<string, array{count:int,preview:list<array<string,mixed>>}>
     */
    private function calendarPreviews(User $viewer, array $filters, CarbonImmutable $from, CarbonImmutable $through): array
    {
        if ($from->gt($through)) {
            return [];
        }
        $dates = DB::query()->selectRaw('? as visit_date', [$from->toDateString()]);
        for ($date = $from->addDay(); $date->lte($through); $date = $date->addDay()) {
            $dates->unionAll(DB::query()->selectRaw('? as visit_date', [$date->toDateString()]));
        }
        $visits = Visit::query()->visibleTo($viewer)->select('user_id')
            ->whereColumn('starts_on', '<=', 'calendar.visit_date')
            ->whereColumn('ends_on', '>=', 'calendar.visit_date');
        $ranked = $this->people($viewer, $filters, $visits)
            ->crossJoinSub($dates, 'calendar')
            ->select('users.id', 'calendar.visit_date')->reorder()
            ->selectRaw('COUNT(*) OVER (PARTITION BY calendar.visit_date) as attendee_count')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY calendar.visit_date ORDER BY (SELECT display_name FROM profiles WHERE profiles.user_id = users.id), users.id) as preview_rank');
        $rows = DB::query()->fromSub($ranked, 'ranked')->where('preview_rank', '<=', 3)->orderBy('preview_rank')->get();
        $people = $this->people($viewer, $filters, Visit::query()->visibleTo($viewer)->overlapping($from, $through)->select('user_id'))
            ->whereIn('users.id', $rows->pluck('id')->unique())->get()->keyBy('id');
        $result = [];
        foreach ($rows->groupBy('visit_date') as $date => $day) {
            $result[$date] = [
                'count' => (int) $day->first()->attendee_count,
                'preview' => array_values($day->map(fn ($row): ?array => isset($people[$row->id]) ? $this->member($people[$row->id], $viewer) : null)->filter()->all()),
            ];
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public function member(User $user, User $viewer): array
    {
        $avatar = $user->profile?->avatar;

        return ['id' => $user->id, 'display_name' => $user->profile?->display_name, 'avatar' => $avatar === null ? null : ['id' => $avatar->id, 'name' => $avatar->name, 'image_url' => route('avatars.image', $avatar, false), 'primary_color' => $avatar->primary_color, 'secondary_color' => $avatar->secondary_color], 'is_match' => (bool) $user->getAttribute('visit_low_match') || (bool) $user->getAttribute('visit_high_match')];
    }
}
