<?php

use App\Models\TripDepartureReport;
use App\Models\User;

it('reviews and confirms a trip dossier without exposing a chat', function (string $device) {
    $report = TripDepartureReport::factory()->for(User::factory()->withProfile(), 'target')->for(User::factory()->withProfile(), 'reporter')->create(['details' => 'Désistement à examiner']);
    $this->actingAs(User::factory()->withProfile()->admin()->create());
    $page = visit("/admin/trip-departure-reports/{$report->id}")->on()->{$device}()->assertSee('Désistement à examiner');
    $page->assertSee('Désistement à examiner')->assertDontSee('Conversation complète')
        ->fill('#trip-decision', 'Examen terminé')->assertValue('#trip-decision', 'Examen terminé')->click('[data-test="close-trip-report"]')
        ->assertPresent('[data-test="trip-close-dialog"]')->click('[data-test="trip-close-cancel"]')->assertValue('#trip-decision', 'Examen terminé');
    expect($report->fresh()->closed_at)->toBeNull();
    $page->assertNoJavaScriptErrors()->assertValue('#trip-decision', 'Examen terminé')->click('[data-test="close-trip-report"]');
    $page->click('[data-test="trip-close-submit"]')
        ->assertSee('Examen terminé')->assertNotPresent('[data-test="close-trip-report"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
    expect($report->fresh()->closed_at)->not->toBeNull();
    $page->click('[data-test="member-ban-trigger"]')->fill('#ban-reason-'.$report->target_user_id, 'Sanction motivée');
    expect($report->target->fresh()->status->value)->toBe('active');
    $page->click('[data-test="confirm-member-ban"]')->assertSee('Lever le bannissement')->assertNoJavaScriptErrors();
    expect($report->target->fresh()->status->value)->toBe('banned');
})->with(['desktop', 'mobile']);
