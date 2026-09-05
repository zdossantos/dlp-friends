<?php

return [
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute field must be a string.',
    'max' => [
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'before_or_equal' => 'The :attribute must be a date before or equal to :date.',
    'custom' => [
        'email' => [
            'required' => 'The email address field is required.',
        ],
    ],
    'attributes' => [
        'email' => 'email address',
        'birth_date' => 'birth date',
        'password' => 'password',
        'pass_display_name' => 'French name of the profile to pass',
        'pass_display_name_en' => 'English name of the profile to pass',
        'pass_bio' => 'French biography of the profile to pass',
        'pass_bio_en' => 'English biography of the profile to pass',
        'like_display_name' => 'French name of the profile to discover',
        'like_display_name_en' => 'English name of the profile to discover',
        'like_bio' => 'French biography of the profile to discover',
        'like_bio_en' => 'English biography of the profile to discover',
    ],
];
