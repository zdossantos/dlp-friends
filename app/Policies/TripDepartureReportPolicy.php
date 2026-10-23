<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\TripDeparture;
use App\Models\TripDepartureReport;
use App\Models\User;

final class TripDepartureReportPolicy
{
    public function create(User $user, TripDeparture $departure): bool
    {
        return $departure->user_id !== null && app(TripPolicy::class)->manage($user, $departure->trip)
            && $departure->left_at->greaterThanOrEqualTo($departure->scheduled_departure_at->subHours(12))
            && $departure->left_at->lessThan($departure->scheduled_departure_at);
    }

    public function viewAny(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->hasRole('admin');
    }

    public function view(User $user, TripDepartureReport $report): bool
    {
        return $this->viewAny($user);
    }

    public function close(User $user, TripDepartureReport $report): bool
    {
        return $this->view($user, $report) && $report->closed_at === null;
    }
}
