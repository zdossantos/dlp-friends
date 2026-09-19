<?php

namespace Tests\Feature\Partner;

use App\Actions\SavePartnerAnnouncement;
use App\Enums\PartnerAnnouncementStatus;
use App\Models\PartnerAnnouncement;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inertia.testing.ensure_pages_exist', false);
    }

    public function test_only_partners_can_open_their_announcement_space(): void
    {
        $member = User::factory()->create();
        $partner = User::factory()->partnerOnly()->create();

        $this->get(route('partner.announcements.index'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('partner.announcements.index'))->assertForbidden();
        $this->actingAs($partner)
            ->get(route('partner.announcements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Partner/Announcements/Index')
                ->where('announcements', []));
    }

    public function test_a_partner_can_create_and_update_only_their_own_draft(): void
    {
        $partner = User::factory()->partnerOnly()->create();
        $profile = PartnerProfile::factory()->for($partner)->create();
        $other = User::factory()->partnerOnly()->create();
        $otherDraft = PartnerAnnouncement::factory()
            ->for(PartnerProfile::factory()->for($other))
            ->create(['destination_url' => 'https://example.com/other']);

        $this->actingAs($partner)->post(route('partner.announcements.store'), [
            'title' => 'Une offre partenaire',
            'content' => 'Découvrez cette offre réservée à la communauté.',
            'destination_url' => 'https://offers.example.com/community',
        ])->assertRedirect(route('partner.announcements.index'));

        $draft = $profile->announcements()->sole();
        expect($draft->status)->toBe(PartnerAnnouncementStatus::Draft);

        $this->actingAs($partner)->put(route('partner.announcements.update', $draft), [
            'title' => 'Une offre mise à jour',
            'content' => 'Le contenu mis à jour reste strictement amical.',
            'destination_url' => 'https://offers.example.com/updated',
        ])->assertRedirect(route('partner.announcements.index'));

        expect($draft->fresh()->title)->toBe('Une offre mise à jour')
            ->and($draft->fresh()->destination_url)->toBe('https://offers.example.com/updated');

        $this->actingAs($partner)
            ->put(route('partner.announcements.update', $otherDraft), $this->validPayload())
            ->assertForbidden();
    }

    public function test_title_content_and_destination_are_validated_when_saving(): void
    {
        $partner = User::factory()->partnerOnly()->create();
        PartnerProfile::factory()->for($partner)->create();

        $this->actingAs($partner)->post(route('partner.announcements.store'), [
            'title' => str_repeat('a', 81),
            'content' => str_repeat('b', 501),
            'destination_url' => 'http://localhost/internal',
        ])->assertSessionHasErrors(['title', 'content', 'destination_url']);

        $this->assertDatabaseCount('partner_announcements', 0);
    }

    public function test_the_action_revalidates_the_destination_when_saving(): void
    {
        $partner = User::factory()->partnerOnly()->create();
        PartnerProfile::factory()->for($partner)->create();

        $this->expectException(ValidationException::class);

        app(SavePartnerAnnouncement::class)->handle($partner, [
            'title' => 'Offre',
            'content' => 'Contenu',
            'destination_url' => 'https://127.0.0.1/internal',
        ]);
    }

    public function test_submission_is_immutable_and_revalidates_the_persisted_destination(): void
    {
        $this->travelTo('2026-09-19 10:00:00');
        $partner = User::factory()->partnerOnly()->create();
        $profile = PartnerProfile::factory()->for($partner)->create();
        $draft = PartnerAnnouncement::factory()->for($profile)->create([
            'destination_url' => 'https://offers.example.com/safe',
        ]);

        $this->actingAs($partner)
            ->post(route('partner.announcements.submit', $draft))
            ->assertRedirect(route('partner.announcements.index'));

        expect($draft->fresh()->status)->toBe(PartnerAnnouncementStatus::PendingApproval)
            ->and($draft->fresh()->submitted_at?->equalTo(now()))->toBeTrue();

        $submittedAttributes = $draft->fresh()->getAttributes();

        $this->actingAs($partner)
            ->put(route('partner.announcements.update', $draft), $this->validPayload())
            ->assertSessionHasErrors('announcement');

        expect($draft->fresh()->getAttributes())->toBe($submittedAttributes);

        $unsafe = PartnerAnnouncement::factory()->for($profile)->create([
            'destination_url' => 'https://127.0.0.1/internal',
        ]);

        $this->actingAs($partner)
            ->post(route('partner.announcements.submit', $unsafe))
            ->assertSessionHasErrors('destination_url');

        expect($unsafe->fresh()->status)->toBe(PartnerAnnouncementStatus::Draft);
    }

    public function test_a_partner_can_delete_a_draft_and_cancel_before_sending_only(): void
    {
        $partner = User::factory()->partnerOnly()->create();
        $profile = PartnerProfile::factory()->for($partner)->create();
        $draft = PartnerAnnouncement::factory()->for($profile)->create([
            'destination_url' => 'https://example.com/draft',
        ]);
        $pending = PartnerAnnouncement::factory()->for($profile)->create([
            'status' => PartnerAnnouncementStatus::PendingApproval,
            'destination_url' => 'https://example.com/pending',
        ]);
        $sending = PartnerAnnouncement::factory()->for($profile)->create([
            'status' => PartnerAnnouncementStatus::Sending,
            'destination_url' => 'https://example.com/sending',
        ]);

        $this->actingAs($partner)
            ->delete(route('partner.announcements.destroy', $draft))
            ->assertRedirect(route('partner.announcements.index'));
        $this->assertModelMissing($draft);

        $this->actingAs($partner)
            ->post(route('partner.announcements.cancel', $pending))
            ->assertRedirect(route('partner.announcements.index'));
        expect($pending->fresh()->status)->toBe(PartnerAnnouncementStatus::Cancelled);

        $this->actingAs($partner)
            ->post(route('partner.announcements.cancel', $sending))
            ->assertSessionHasErrors('decision');
        expect($sending->fresh()->status)->toBe(PartnerAnnouncementStatus::Sending);
    }

    public function test_the_index_exposes_only_owned_announcements_and_locked_actions(): void
    {
        $partner = User::factory()->partnerOnly()->create();
        $profile = PartnerProfile::factory()->for($partner)->create();
        $draft = PartnerAnnouncement::factory()->for($profile)->create([
            'title' => 'Mon brouillon',
            'destination_url' => 'https://example.com/mine',
        ]);
        PartnerAnnouncement::factory()->create([
            'title' => 'Annonce étrangère',
            'destination_url' => 'https://example.com/other',
        ]);

        $this->actingAs($partner)
            ->get(route('partner.announcements.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('announcements', 1)
                ->where('announcements.0.id', $draft->id)
                ->where('announcements.0.title', 'Mon brouillon')
                ->where('announcements.0.canEdit', true)
                ->where('announcements.0.canSubmit', true)
                ->where('announcements.0.canCancel', false));
    }

    /** @return array{title: string, content: string, destination_url: string} */
    private function validPayload(): array
    {
        return [
            'title' => 'Annonce valide',
            'content' => 'Une annonce destinée à la communauté.',
            'destination_url' => 'https://offers.example.com/valid',
        ];
    }
}
