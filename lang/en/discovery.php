<?php

return [
    'navigation' => 'Explore',
    'actions' => [
        'pass' => 'Pass',
        'discover' => 'Discover',
        'pass_profile' => 'Pass this profile',
        'discover_profile' => 'Discover this profile',
    ],
    'page' => [
        'title' => 'Explore', 'description' => 'Members who share your favourite worlds.', 'error_title' => 'Decision not saved',
        'generic_error' => 'Something went wrong.', 'server_error' => 'The server could not save this decision.',
        'network_error' => 'The connection failed before this decision was saved.', 'retry' => 'Try again', 'loading' => 'Looking for profiles…',
        'empty_title' => 'You have explored every available profile', 'empty_description' => 'Come back later or update your profile to better reflect your favourite worlds.',
        'profile' => 'My profile', 'profiles_label' => 'Profiles to discover',
    ],
    'card' => [
        'label' => 'Profile to discover: :name', 'avatar_alt' => ':name avatar', 'age' => ':age years old',
        'common_interest' => ':count favourite world in common', 'common_interests' => ':count favourite worlds in common',
        'empty_bio' => 'No bio yet.', 'frequency_unknown' => 'Frequency not provided', 'frequency_rarely' => 'Rarely',
        'frequency_sometimes' => 'Sometimes', 'frequency_often' => 'Often', 'frequency_very_often' => 'Very often',
        'instructions_pass' => 'Swipe left or use the left arrow key to pass this profile.',
        'instructions_discover' => 'Swipe right or use the right arrow key to discover this profile.',
        'instructions_both' => 'Swipe left to pass this profile or right to discover it. Use the left and right arrow keys with a keyboard.',
        'actions_label' => 'Profile actions',
    ],
    'match' => [
        'label' => 'Crossed worlds',
        'title' => 'Your worlds cross paths',
        'description' => ':name would like to discover you too. You can now start chatting.',
        'open_conversation' => 'Start chatting',
        'continue' => 'Keep exploring',
    ],
];
