<?php

return [
    'exports' => [
        'disk' => env('DATA_EXPORT_DISK', 'exports'),
        'expires_hours' => (int) env('DATA_EXPORT_EXPIRES_HOURS', 24),
        'cooldown_hours' => (int) env('DATA_EXPORT_COOLDOWN_HOURS', 24),
    ],
    'deletion' => [
        'purge_days' => (int) env('ACCOUNT_DELETION_PURGE_DAYS', 30),
    ],
];
