<?php

return [
    'validation' => [
        'full' => 'This trip is full.',
        'pending' => 'This request is no longer pending.',
        'local_time' => 'This local time does not exist in Paris.',
        'future' => 'Departure must be in the future.',
        'horizon' => 'Departure must be within the next twelve months.',
        'confirm_members' => 'Confirm sharing with all members.',
        'closed' => 'This trip has departed or been cancelled.',
        'locked' => 'This field cannot change after the first acceptance.',
        'capacity' => 'Seats cannot be fewer than accepted participants.',
    ],
    'attributes' => [
        'reason' => 'reason',
        'confirmed' => 'confirmation',
        'transport' => 'transport',
        'direction' => 'direction',
        'general_departure' => 'general departure',
        'destination' => 'destination',
        'departs_at' => 'departure time',
        'meeting_point' => 'meeting point',
        'audience' => 'audience',
        'car_seats' => 'passenger seats',
    ],
];
