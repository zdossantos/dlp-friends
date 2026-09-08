<?php

it('shares generated personal data exports between application containers', function () {
    $compose = file_get_contents(base_path('compose.yaml'));

    expect($compose)
        ->toContain('data-exports:/var/www/html/storage/app/private/data-exports')
        ->and(substr_count($compose, 'data-exports:/var/www/html/storage/app/private/data-exports'))
        ->toBeGreaterThanOrEqual(3)
        ->and($compose)->toContain("volumes:\n  mysql-data:\n  minio-data:\n  data-exports:");
});
