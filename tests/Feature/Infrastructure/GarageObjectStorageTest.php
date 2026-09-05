<?php

it('supports the private object operations used by the application', function () {
    if (env('GARAGE_INTEGRATION') !== '1') {
        $this->markTestSkipped('Set GARAGE_INTEGRATION=1 to test a prepared Garage bucket.');
    }

    $disk = Storage::build([
        'driver' => 's3',
        'key' => env('GARAGE_TEST_ACCESS_KEY'),
        'secret' => env('GARAGE_TEST_SECRET_KEY'),
        'region' => env('GARAGE_TEST_REGION', 'garage'),
        'bucket' => env('GARAGE_TEST_BUCKET', 'dlp-friends-test'),
        'endpoint' => env('GARAGE_TEST_ENDPOINT', 'http://127.0.0.1:3900'),
        'use_path_style_endpoint' => true,
        'throw' => true,
    ]);

    $path = 'integration/'.bin2hex(random_bytes(12)).'.txt';
    $contents = 'garage-integration-'.bin2hex(random_bytes(16));

    try {
        expect($disk->put($path, $contents))->toBeTrue()
            ->and($disk->exists($path))->toBeTrue()
            ->and($disk->get($path))->toBe($contents)
            ->and(stream_get_contents($disk->readStream($path)))->toBe($contents)
            ->and($disk->delete($path))->toBeTrue()
            ->and($disk->missing($path))->toBeTrue();
    } finally {
        $disk->delete($path);
    }
});

it('does not require temporary S3 URLs for the current application', function () {
    $source = file_get_contents(app_path('Http/Controllers/AvatarImageController.php'));

    expect($source)->toContain('Storage::response(')
        ->not->toContain('temporaryUrl(')
        ->not->toContain('temporaryUploadUrl(');
});
