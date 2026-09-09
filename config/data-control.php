<?php

return [
    'deletion' => [
        'purge_days' => (int) env('ACCOUNT_DELETION_PURGE_DAYS', 30),
    ],
];
