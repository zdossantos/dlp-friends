<?php

return [
    'required' => 'The :attribute field is required.',
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
    ],
];
