<?php

namespace Tests\Feature\Partner;

use App\Actions\SavePartnerProfileDraft;
use App\Enums\PartnerRevisionStatus;
use App\Models\PartnerProfile;
use App\Models\PartnerProfileRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PartnerImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.default', 's3');
        Storage::fake('s3');
    }

    public function test_uploaded_image_is_resized_and_reencoded_as_metadata_free_webp(): void
    {
        $partner = User::factory()->partnerOnly()->create();
        $source = UploadedFile::fake()->image('partner.png', 2400, 1200);
        $marker = 'PRIVATE_METADATA_MARKER';
        $upload = UploadedFile::fake()->createWithContent(
            'partner.png',
            file_get_contents($source->getPathname()).$marker,
        );

        $this->actingAs($partner)->put(route('partner.profile.update'), [
            ...$this->validPayload(),
            'image' => $upload,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $revision = $partner->refresh()->partnerProfile->revisions()->sole();
        $contents = Storage::disk('s3')->get($revision->image_path);
        $dimensions = getimagesizefromstring($contents);

        expect($revision->image_path)->toStartWith('partners/')
            ->toEndWith('.webp')
            ->and($contents)->not->toContain($marker)
            ->and($dimensions)->not->toBeFalse()
            ->and($dimensions[0])->toBe(1600)
            ->and($dimensions[1])->toBe(800)
            ->and($dimensions['mime'])->toBe('image/webp');
    }

    public function test_jpeg_exif_orientation_is_applied_before_resizing(): void
    {
        $partner = User::factory()->partnerOnly()->create();
        $source = UploadedFile::fake()->image('portrait.jpg', 800, 1200);
        $oriented = UploadedFile::fake()->createWithContent(
            'portrait.jpg',
            $this->withExifOrientation(file_get_contents($source->getPathname()), 6),
        );

        $this->actingAs($partner)->put(route('partner.profile.update'), [
            ...$this->validPayload(),
            'image' => $oriented,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $revision = $partner->refresh()->partnerProfile->revisions()->sole();
        $dimensions = getimagesizefromstring(Storage::disk('s3')->get($revision->image_path));

        expect($dimensions)->not->toBeFalse()
            ->and($dimensions[0])->toBe(1200)
            ->and($dimensions[1])->toBe(800);
    }

    public function test_replacing_a_draft_image_deletes_the_unreferenced_previous_file(): void
    {
        $partner = User::factory()->partnerOnly()->create();

        $this->actingAs($partner)->put(route('partner.profile.update'), [
            ...$this->validPayload(),
            'image' => UploadedFile::fake()->image('first.png', 800, 600),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $draft = $partner->refresh()->partnerProfile->revisions()->sole();
        $previousImagePath = $draft->image_path;
        Storage::disk('s3')->assertExists($previousImagePath);

        $this->actingAs($partner)->put(route('partner.profile.update'), [
            ...$this->validPayload(),
            'image' => UploadedFile::fake()->image('replacement.png', 800, 600),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $replacementImagePath = $draft->refresh()->image_path;

        expect($replacementImagePath)->not->toBe($previousImagePath);
        Storage::disk('s3')->assertMissing($previousImagePath);
        Storage::disk('s3')->assertExists($replacementImagePath);
    }

    public function test_replacing_a_draft_image_keeps_the_previous_file_referenced_by_a_submission(): void
    {
        $partner = User::factory()->partnerOnly()->create();
        $profile = PartnerProfile::factory()->for($partner)->create();
        $sharedImagePath = 'partners/shared.webp';
        Storage::disk('s3')->put($sharedImagePath, 'shared-image');
        $submission = PartnerProfileRevision::factory()->for($profile)->create([
            'image_path' => $sharedImagePath,
            'status' => PartnerRevisionStatus::PendingApproval,
            'submitted_at' => now(),
            'draft_key' => null,
        ]);
        $draft = PartnerProfileRevision::factory()->for($profile)->create([
            'image_path' => $sharedImagePath,
        ]);

        $this->actingAs($partner)->put(route('partner.profile.update'), [
            ...$this->validPayload(),
            'image' => UploadedFile::fake()->image('replacement.png', 800, 600),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $replacementImagePath = $draft->refresh()->image_path;

        expect($replacementImagePath)->not->toBe($sharedImagePath)
            ->and($submission->fresh()->image_path)->toBe($sharedImagePath);
        Storage::disk('s3')->assertExists($sharedImagePath);
        Storage::disk('s3')->assertExists($replacementImagePath);
    }

    public function test_image_validation_rejects_unsupported_too_small_and_oversized_files(): void
    {
        $partner = User::factory()->partnerOnly()->create();
        $small = UploadedFile::fake()->image('small.png', 639, 360);
        $largeDimensions = UploadedFile::fake()->image('large.png', 6001, 360);
        $base = UploadedFile::fake()->image('heavy.png', 640, 360);
        $oversized = UploadedFile::fake()->createWithContent(
            'heavy.png',
            file_get_contents($base->getPathname()).str_repeat('x', 5 * 1024 * 1024),
        );

        foreach ([
            UploadedFile::fake()->create('partner.gif', 10, 'image/gif'),
            $small,
            $largeDimensions,
            $oversized,
        ] as $image) {
            $this->actingAs($partner)->put(route('partner.profile.update'), [
                ...$this->validPayload(),
                'image' => $image,
            ])->assertSessionHasErrors('image');
        }

        $this->assertDatabaseCount('partner_profile_revisions', 0);
        Storage::disk('s3')->assertDirectoryEmpty('partners');
    }

    public function test_a_new_image_is_deleted_when_the_draft_transaction_fails(): void
    {
        $partner = User::factory()->partnerOnly()->create();
        PartnerProfileRevision::creating(function (): never {
            throw new RuntimeException('Forced transaction failure.');
        });

        try {
            app(SavePartnerProfileDraft::class)->handle(
                $partner,
                $this->validPayload(),
                UploadedFile::fake()->image('partner.webp', 800, 600),
            );
            $this->fail('The forced transaction failure should have propagated.');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toBe('Forced transaction failure.');
        }

        $this->assertDatabaseCount('partner_profiles', 0);
        $this->assertDatabaseCount('partner_profile_revisions', 0);
        Storage::disk('s3')->assertDirectoryEmpty('partners');
    }

    public function test_private_revision_images_are_streamed_only_to_the_owner_or_an_administrator(): void
    {
        $owner = User::factory()->partnerOnly()->create();
        $otherPartner = User::factory()->partnerOnly()->create();
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $profile = PartnerProfile::factory()->for($owner)->create();
        $revision = PartnerProfileRevision::factory()->for($profile)->create([
            'image_path' => 'partners/private.webp',
        ]);
        Storage::disk('s3')->put($revision->image_path, 'private-image');

        $this->get(route('partner.profile-revisions.image', $revision))
            ->assertRedirect(route('login'));
        $this->actingAs($member)
            ->get(route('partner.profile-revisions.image', $revision))
            ->assertForbidden();
        $this->actingAs($otherPartner)
            ->get(route('partner.profile-revisions.image', $revision))
            ->assertForbidden();

        expect($this->actingAs($owner)
            ->get(route('partner.profile-revisions.image', $revision))
            ->assertOk()
            ->streamedContent())->toBe('private-image');
        expect($this->actingAs($admin)
            ->get(route('partner.profile-revisions.image', $revision))
            ->assertOk()
            ->streamedContent())->toBe('private-image');
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

    private function withExifOrientation(string $jpeg, int $orientation): string
    {
        $tiff = "II\x2A\x00\x08\x00\x00\x00"
            ."\x01\x00"
            ."\x12\x01\x03\x00\x01\x00\x00\x00"
            .pack('v', $orientation)."\x00\x00"
            ."\x00\x00\x00\x00";
        $exif = "Exif\x00\x00".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }
}
