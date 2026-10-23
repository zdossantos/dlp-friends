<?php

namespace App\Actions;

use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripDepartureReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class ReportTripDeparture
{
    public function handle(User $organizer, TripDeparture $departure, string $details): TripDepartureReport
    {
        return DB::transaction(function () use ($organizer, $departure, $details): TripDepartureReport {
            $accounts = app(LockTripAccounts::class)->handle([$organizer->id, $departure->user_id]);
            $organizer = $accounts->get($organizer->id) ?? abort(403);
            $trip = Trip::query()->lockForUpdate()->findOrFail($departure->trip_id);
            $departure = TripDeparture::query()->lockForUpdate()->findOrFail($departure->id);
            $departure->setRelation('trip', $trip);
            Gate::forUser($organizer)->authorize('create', [TripDepartureReport::class, $departure]);
            Validator::make(['details' => $details], ['details' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/u']])->validate();

            return TripDepartureReport::query()->firstOrCreate(['open_key' => $departure->id.':'.$organizer->id], [
                'trip_departure_id' => $departure->id, 'reporter_user_id' => $organizer->id, 'target_user_id' => $departure->user_id,
                'scheduled_departure_at' => $departure->scheduled_departure_at, 'left_at' => $departure->left_at, 'details' => $details,
            ]);
        }, 3);
    }
}
