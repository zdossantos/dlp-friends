<?php

it('serves the worker and manifest with safe production cache policies', function () {
    $config = file_get_contents(base_path('docker/nginx/default.conf'));

    expect($config)
        ->toContain('location = /service-worker.js')
        ->toContain('Cache-Control "no-cache, no-store, must-revalidate"')
        ->toContain('Service-Worker-Allowed "/"')
        ->toContain('location = /app.webmanifest')
        ->toContain('application/manifest+json')
        ->toContain('location ^~ /build/assets/')
        ->toContain('Cache-Control "public, immutable"');

    expect(public_path('app.webmanifest'))->toBeFile()
        ->and(public_path('offline.html'))->toBeFile();
});
