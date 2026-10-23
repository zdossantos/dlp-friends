<?php

use App\Actions\CloseTripDepartureReport;
use App\Actions\ReportTripDeparture;
use App\Actions\RequestTripParticipation;
use App\Actions\WithdrawFromTrip;
use App\Models\GroupChat;
use App\Models\ModerationAudit;
use App\Models\Trip;
use App\Models\TripDepartureReport;
use App\Models\TripRegistration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-24 00:00:00', 'UTC'));
    $this->organizer = User::factory()->withProfile()->create();
    $this->member = User::factory()->withProfile()->create();
    $this->trip = Trip::factory()->for($this->organizer, 'organizer')->create(['audience' => 'members', 'departs_at' => CarbonImmutable::parse('2026-10-25 10:00:00', 'UTC')]);
    $this->chat = GroupChat::query()->create(['trip_id' => $this->trip->id]);
    $this->registration = TripRegistration::factory()->for($this->trip)->for($this->member, 'user')->create(['status' => 'accepted']);
    $this->leaveAt = function (int $seconds = -3600) {
        $this->travelTo($this->trip->departs_at->addSeconds($seconds));
        app(WithdrawFromTrip::class)->handle($this->member, $this->trip, 'Imprévu personnel');

        return $this->trip->departures()->sole();
    };
});

test('only voluntary accepted exits in the exact twelve hour interval can be reported', function (int $seconds, bool $allowed) {
    $departure = ($this->leaveAt)($seconds);
    // The dossier remains available later; eligibility follows the immutable exit instant.
    $this->travelTo($this->trip->departs_at->addDay());
    if (! $allowed) {
        expect(fn () => app(ReportTripDeparture::class)->handle($this->organizer, $departure, 'Désistement tardif'))->toThrow(AuthorizationException::class);

        return;
    }
    $report = app(ReportTripDeparture::class)->handle($this->organizer, $departure, 'Désistement tardif');
    expect($report->target_user_id)->toBe($this->member->id)
        ->and($report->scheduled_departure_at->equalTo($this->trip->departs_at))->toBeTrue()
        ->and($report->left_at->equalTo($departure->left_at))->toBeTrue();
})->with([[-43200, true], [-43201, false], [-1, true], [0, false], [1, false]]);

test('a new request preserves the historical proof and repeat reporting is idempotent', function () {
    $departure = ($this->leaveAt)();
    app(RequestTripParticipation::class)->handle($this->member, $this->trip);
    Notification::fake();
    $report = app(ReportTripDeparture::class)->handle($this->organizer, $departure, 'Explication');
    $repeated = app(ReportTripDeparture::class)->handle($this->organizer, $departure, 'Autre texte');
    expect($repeated->id)->toBe($report->id)->and($report->details)->toBe('Explication')
        ->and(TripDepartureReport::query()->count())->toBe(1)
        ->and($this->member->fresh()->status->value)->toBe('active');
    Notification::assertNothingSent();
    expect(Gate::forUser(User::factory()->admin()->create())->allows('view', $this->chat))->toBeFalse();
});

test('report explanation must be present nonblank and bounded even for action callers', function (string $details) {
    $departure = ($this->leaveAt)();
    expect(fn () => app(ReportTripDeparture::class)->handle($this->organizer, $departure, $details))->toThrow(ValidationException::class);
})->with(['', '   ', str_repeat('a', 1001)]);

test('only the active organizer can report and HTTP requires confirmation', function () {
    $departure = ($this->leaveAt)();
    $url = route('trip-departures.reports.store', $departure);
    $payload = ['details' => 'Motif', 'confirmed' => true];
    $this->actingAs($this->member)->post($url, $payload)->assertForbidden();
    $this->actingAs(User::factory()->withProfile()->create())->post($url, $payload)->assertForbidden();
    $this->actingAs($this->organizer)->post($url, ['details' => 'Motif'])->assertSessionHasErrors('confirmed');
    $this->post($url, $payload)->assertRedirect();
    expect(TripDepartureReport::query()->count())->toBe(1);
});

test('forced or pending exits have no reportable proof', function (string $status) {
    $this->registration->update(['status' => $status]);
    if ($status === 'pending') {
        app(WithdrawFromTrip::class)->handle($this->member, $this->trip, null);
    }
    expect($this->trip->departures()->count())->toBe(0);
    $this->actingAs($this->organizer)->post('/trip-departures/999999/reports', ['details' => 'Motif', 'confirmed' => true])->assertNotFound();
})->with(['pending', 'removed', 'blocked']);

test('administrative review is dossier only audited and remains available after trip purge', function () {
    $departure = ($this->leaveAt)();
    $report = app(ReportTripDeparture::class)->handle($this->organizer, $departure, 'Explication privée');
    $admin = User::factory()->withProfile()->admin()->create();
    $url = route('admin.trip-departure-reports.show', $report);
    $this->actingAs($this->organizer)->get($url)->assertForbidden();
    $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('Admin/TripDepartureReports/Show', false)
        ->where('report.details', 'Explication privée')->missing('messages')->missing('trip')->missing('report.meeting_point')->missing('report.participants'));
    expect(ModerationAudit::query()->where('trip_departure_report_id', $report->id)->where('operation', 'view')->count())->toBe(1);
    expect(Gate::forUser($admin)->allows('view', $this->chat))->toBeFalse();
    $this->trip->delete();
    expect($report->fresh()->trip_departure_id)->toBeNull();
    $this->get($url)->assertOk();
    app(CloseTripDepartureReport::class)->handle($admin, $report, 'Examiné sans sanction');
    app(CloseTripDepartureReport::class)->handle($admin, $report, 'Reprise');
    expect($report->fresh()->decision)->toBe('Examiné sans sanction')
        ->and($report->fresh()->open_key)->toBeNull()
        ->and(ModerationAudit::query()->where('trip_departure_report_id', $report->id)->where('operation', 'close')->count())->toBe(1);
    $admin->forceFill(['status' => 'banned'])->save();
    expect(fn () => app(CloseTripDepartureReport::class)->handle($admin, $report, 'Autre décision'))->toThrow(AuthorizationException::class);
});

test('closing requires an active administrator bounded decision and confirmation', function () {
    $report = app(ReportTripDeparture::class)->handle($this->organizer, ($this->leaveAt)(), 'Motif');
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->patch(route('admin.trip-departure-reports.close', $report), ['decision' => 'Examiné'])->assertSessionHasErrors('confirmed');
    foreach (['', ' ', str_repeat('x', 1001)] as $decision) {
        expect(fn () => app(CloseTripDepartureReport::class)->handle($admin, $report, $decision))->toThrow(ValidationException::class);
    }
});

test('received report history includes only the target trip dossiers', function () {
    $report = app(ReportTripDeparture::class)->handle($this->organizer, ($this->leaveAt)(), 'Motif');
    TripDepartureReport::factory()->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.conversation-reports.index', ['target' => $this->member->id]))
        ->assertOk()->assertInertia(fn ($page) => $page->has('tripReports.data', 1)->where('tripReports.data.0.id', $report->id));
});
