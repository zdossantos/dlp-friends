<?php

namespace Tests\Feature\Partner;

use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.default', 's3');
        Storage::fake('s3');
    }

    public function test_only_partners_can_open_and_save_the_partner_profile(): void
    {
        $member = User::factory()->create();
        $partner = User::factory()->partnerOnly()->create();

        $this->get(route('partner.profile.edit'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('partner.profile.edit'))->assertForbidden();
        $this->actingAs($member)->put(route('partner.profile.update'), $this->validPayload())
            ->assertForbidden();

        config()->set('inertia.testing.ensure_pages_exist', false);

        $this->actingAs($partner)
            ->get(route('partner.profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Partner/Profile/Edit')
                ->where('publishedRevision', null)
                ->where('draft', null)
                ->where('latestSubmission', null));
    }

    public function test_partner_profile_requires_bilingual_names_and_descriptions(): void
    {
        $partner = User::factory()->partnerOnly()->create();

        $this->actingAs($partner)->put(route('partner.profile.update'), [
            'name_fr' => '',
            'name_en' => str_repeat('a', 101),
            'description_fr' => '',
            'description_en' => str_repeat('b', 501),
        ])->assertSessionHasErrors([
            'name_fr',
            'name_en',
            'description_fr',
            'description_en',
        ]);

        $this->assertDatabaseCount('partner_profiles', 0);
        $this->assertDatabaseCount('partner_profile_revisions', 0);
    }

    public function test_saving_replaces_the_single_mutable_draft_without_changing_the_published_revision(): void
    {
        $partner = User::factory()->partnerOnly()->create();
        $profile = PartnerProfile::factory()->for($partner)->published()->create();
        $published = $profile->publishedRevision;

        $this->actingAs($partner)->put(route('partner.profile.update'), [
            ...$this->validPayload(),
            'image' => UploadedFile::fake()->image('partner.jpg', 1200, 800),
        ])->assertRedirect(route('partner.profile.edit'));

        $draft = $profile->revisions()->where('status', PartnerRevisionStatus::Draft)->sole();

        $this->actingAs($partner)->put(route('partner.profile.update'), [
            ...$this->validPayload(),
            'name_fr' => 'Fiche modifiée',
            'name_en' => 'Updated profile',
        ])->assertRedirect(route('partner.profile.edit'));

        expect($profile->fresh()->published_revision_id)->toBe($published->id)
            ->and($profile->revisions()->where('status', PartnerRevisionStatus::Draft)->count())->toBe(1)
            ->and($draft->fresh()->name_fr)->toBe('Fiche modifiée')
            ->and($draft->fresh()->name_en)->toBe('Updated profile')
            ->and($draft->fresh()->image_path)->not->toBeNull()
            ->and($published->fresh()->getAttributes())->toBe($published->getAttributes());
    }

    public function test_a_draft_needs_an_image_before_its_first_submission(): void
    {
        $partner = User::factory()->partnerOnly()->create();

        $this->actingAs($partner)->put(route('partner.profile.update'), $this->validPayload())
            ->assertRedirect(route('partner.profile.edit'));

        $this->actingAs($partner)->post(route('partner.profile.submit'))
            ->assertSessionHasErrors('image');

        $revision = $partner->refresh()->partnerProfile->revisions()->sole();
        expect($revision->status)->toBe(PartnerRevisionStatus::Draft)
            ->and($revision->draft_key)->toBe(1)
            ->and($revision->submitted_at)->toBeNull();
    }

    public function test_submission_is_immutable_and_a_later_save_creates_a_new_draft(): void
    {
        $partner = User::factory()->partnerOnly()->create();

        $this->actingAs($partner)->put(route('partner.profile.update'), [
            ...$this->validPayload(),
            'image' => UploadedFile::fake()->image('partner.png', 800, 600),
        ]);
        $submitted = $partner->refresh()->partnerProfile->revisions()->sole();

        $this->actingAs($partner)->post(route('partner.profile.submit'))
            ->assertRedirect(route('partner.profile.edit'));

        expect($submitted->fresh()->status)->toBe(PartnerRevisionStatus::PendingApproval)
            ->and($submitted->fresh()->submitted_at)->not->toBeNull()
            ->and($submitted->fresh()->draft_key)->toBeNull();

        $submittedAttributes = $submitted->fresh()->getAttributes();

        $this->actingAs($partner)->put(route('partner.profile.update'), [
            ...$this->validPayload(),
            'name_fr' => 'Prochaine version',
            'name_en' => 'Next version',
        ])->assertRedirect(route('partner.profile.edit'));

        expect($submitted->fresh()->getAttributes())->toBe($submittedAttributes)
            ->and($partner->refresh()->partnerProfile->revisions()->count())->toBe(2);

        $draft = $partner->partnerProfile->revisions()
            ->where('status', PartnerRevisionStatus::Draft)
            ->sole();
        expect($draft->name_fr)->toBe('Prochaine version')
            ->and($draft->image_path)->toBe($submitted->image_path);
    }

    public function test_edit_page_exposes_published_draft_and_latest_submission_without_storage_paths(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $partner = User::factory()->partnerOnly()->create();
        $profile = PartnerProfile::factory()->for($partner)->published()->create();
        $published = $profile->publishedRevision;
        $draft = PartnerProfileRevision::factory()->for($profile)->create([
            'name_fr' => 'Brouillon',
            'name_en' => 'Draft',
        ]);
        $pending = PartnerProfileRevision::factory()->for($profile)->create([
            'status' => PartnerRevisionStatus::PendingApproval,
            'submitted_at' => now(),
            'draft_key' => null,
        ]);

        $this->actingAs($partner)
            ->get(route('partner.profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Partner/Profile/Edit')
                ->where('publishedRevision.id', $published->id)
                ->where('publishedRevision.imageUrl', route('partner.profile-revisions.image', $published))
                ->missing('publishedRevision.image_path')
                ->where('draft.id', $draft->id)
                ->where('draft.nameFr', 'Brouillon')
                ->where('draft.imageUrl', route('partner.profile-revisions.image', $draft))
                ->missing('draft.image_path')
                ->where('latestSubmission.id', $pending->id)
                ->where('latestSubmission.status', PartnerRevisionStatus::PendingApproval->value)
                ->where('latestSubmission.submittedAt', $pending->submitted_at?->toIso8601String())
                ->missing('latestSubmission.image_path'));
    }

    public function test_edit_page_does_not_present_a_depublished_revision_as_currently_published(): void
    {
        config()->set('inertia.testing.ensure_pages_exist', false);
        $partner = User::factory()->partnerOnly()->create();
        $profile = PartnerProfile::factory()->for($partner)->published()->create();
        $profile->update(['is_published' => false]);

        $this->actingAs($partner)
            ->get(route('partner.profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('publishedRevision', null));
    }

    /** @return array{name_fr: string, name_en: string, description_fr: string, description_en: string} */
    private function validPayload(): array
    {
        return [
            'name_fr' => 'Partenaire francophone',
            'name_en' => 'English partner',
            'description_fr' => 'Une description française complète.',
            'description_en' => 'A complete English description.',
        ];
    }
}
