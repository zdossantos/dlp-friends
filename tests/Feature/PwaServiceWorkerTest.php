<?php

it('ships a standalone offline page without member data', function () {
    $offlinePath = public_path('offline.html');

    expect($offlinePath)->toBeFile();

    $offline = file_get_contents($offlinePath);

    expect($offline)
        ->toContain('DLP Friends')
        ->not->toContain('@inertia')
        ->not->toContain('csrf-token');
});

it('builds a stable service worker entry point', function () {
    $workerPath = public_path('service-worker.js');

    expect($workerPath)->toBeFile();

    $worker = file_get_contents($workerPath);

    expect($worker)
        ->not->toContain('"url":"storage/')
        ->not->toContain('"url":"api/')
        ->not->toContain('"url":"conversations/')
        ->not->toContain('"url":"notifications/');
});
