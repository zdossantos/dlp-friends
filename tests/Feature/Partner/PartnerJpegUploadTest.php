<?php

use App\Enums\PartnerRevisionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('saves and submits a partner profile with a 1125 by 1088 JPEG of 202 KiB', function () {
    config()->set('filesystems.default', 's3');
    Storage::fake('s3');
    $partner = User::factory()->partnerOnly()->create();
    $source = UploadedFile::fake()->image('partner.jpg', 1125, 1088);
    $image = UploadedFile::fake()->createWithContent(
        'partner.jpg',
        str_pad(file_get_contents($source->getPathname()), 202 * 1024, "\0"),
    );

    $this->actingAs($partner)->post(route('partner.profile.update', ['_method' => 'PUT']), [
        'name_fr' => 'Atelier des amis',
        'name_en' => 'Friends workshop',
        'description_fr' => 'Des idées pour une journée entre amis.',
        'description_en' => 'Ideas for a day with friends.',
        'image' => $image,
    ])->assertRedirect(route('partner.profile.edit'))->assertSessionHasNoErrors();

    $this->post(route('partner.profile.submit'))
        ->assertRedirect(route('partner.profile.edit'))
        ->assertSessionHasNoErrors();

    $revision = $partner->fresh()->partnerProfile->revisions()->sole();
    expect($revision->status)->toBe(PartnerRevisionStatus::PendingApproval);
    $dimensions = getimagesizefromstring(Storage::disk('s3')->get($revision->image_path));
    expect($dimensions['mime'])->toBe('image/webp')
        ->and($dimensions[0])->toBe(931)
        ->and($dimensions[1])->toBe(900);
});
