<?php

namespace App\Data;

use App\Models\Profile;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

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
        $days = [];
        for ($date = $month; $date->month === $month->month; $date = $date->addDay()) {
            if ($date->lt($today)) {
                continue;
            }
            $people = $this->attendees($viewer, $filters, $date);
            $count = (clone $people)->count();
            $preview = $people->limit(3)->get()->map(fn (User $user): array => $this->member($user, $viewer))->all();
            $days[] = ['date' => $date->toDateString(), 'isToday' => $date->isSameDay($today), 'ownVisitIds' => $own->filter(fn (Visit $visit): bool => $visit->starts_on->toDateString() <= $date->toDateString() && $visit->ends_on->toDateString() >= $date->toDateString())->pluck('id')->values()->all(), 'otherCount' => $count, 'preview' => $preview];
        }

        return ['filters' => $filters, 'visibility' => $viewer->visits_visibility->value, 'ownVisits' => $own->map(fn (Visit $visit): array => ['id' => $visit->id, 'starts_on' => $visit->starts_on->toDateString(), 'ends_on' => $visit->ends_on->toDateString()])->all(), 'days' => $days, 'closeHref' => route('visits.index', $filters, false), 'panel' => null];
    }

    /**
     * @param  array{month:string,day:string,view:string,audience:string,search:string,member:int|null}  $filters
     * @return Builder<User>
     */
    public function attendees(User $viewer, array $filters, CarbonImmutable $date): Builder
    {
        $visits = Visit::query()->visibleTo($viewer)->overlapping($date, $date)->select('user_id');
        $query = User::query()->where('users.id', '!=', $viewer->id)->whereIn('users.id', $visits)
            ->with('profile.avatar')
            ->withExists(['lowMatches as visit_low_match' => fn (Builder $match) => $match->where('user_high_id', $viewer->id), 'highMatches as visit_high_match' => fn (Builder $match) => $match->where('user_low_id', $viewer->id)])
            ->orderBy(Profile::query()->select('display_name')->whereColumn('profiles.user_id', 'users.id'))->orderBy('users.id');
        if ($date->startOfDay()->lt(CarbonImmutable::today('Europe/Paris'))) {
            $query->whereRaw('1 = 0');
        }
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

    /** @return array<string,mixed> */
    public function member(User $user, User $viewer): array
    {
        $avatar = $user->profile?->avatar;

        return ['id' => $user->id, 'display_name' => $user->profile?->display_name, 'avatar' => $avatar === null ? null : ['id' => $avatar->id, 'name' => $avatar->name, 'image_url' => route('avatars.image', $avatar, false), 'primary_color' => $avatar->primary_color, 'secondary_color' => $avatar->secondary_color], 'is_match' => (bool) $user->getAttribute('visit_low_match') || (bool) $user->getAttribute('visit_high_match')];
    }
}
