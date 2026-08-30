<?php

return [
    'interest_limit' => 'You can select one favorite world at most.|You can select up to :max favorite worlds.',
    'navigation' => 'Profile',
    'create' => [
        'title' => 'Create my profile', 'heading' => 'Let’s create your profile',
        'description' => 'This information will help other members discover you.',
        'empty_catalogue' => 'The catalogue is empty.', 'add_first_avatar' => 'Add the first avatar',
        'empty_catalogue_suffix' => 'to allow profiles to be created.', 'submit' => 'Create my profile',
    ],
    'edit' => ['title' => 'Edit my profile', 'breadcrumb' => 'Edit', 'submit' => 'Save'],
    'actions' => [
        'label' => 'Profile actions', 'settings' => 'Settings', 'administration' => 'Administration',
        'logout' => 'Log out', 'edit' => 'Edit my profile', 'back' => 'Back', 'previous' => 'Back', 'next' => 'Next',
    ],
    'details' => [
        'age' => ':age years old', 'about' => 'About', 'interests' => 'Favorite worlds',
        'empty_bio' => 'No bio provided.', 'visit_frequency' => 'Visit frequency',
        'frequency_rarely' => 'Rarely', 'frequency_sometimes' => 'Sometimes', 'frequency_often' => 'Often',
        'frequency_very_often' => 'Very often', 'frequency_unknown' => 'Not provided',
        'visible' => 'Visible', 'hidden' => 'Hidden',
    ],
    'avatar' => [
        'image_alt' => ':name avatar', 'carousel_role' => 'carousel', 'selected_avatar' => 'Selected avatar: :name',
        'selected' => ':name, selected', 'choose' => 'Choose :name', 'selected_badge' => 'Selected',
        'previous' => 'Previous avatar', 'next' => 'Next avatar', 'instructions' => 'Swipe or use the arrow keys',
    ],
    'interests' => ['title' => 'Favorite worlds', 'add' => 'Add :name', 'remove' => 'Remove :name'],
    'form' => [
        'steps' => ['avatar' => 'Avatar', 'identity' => 'Identity', 'affinities' => 'Worlds', 'preview' => 'Preview'],
        'avatar_title' => 'Your avatar', 'avatar_description' => 'Choose the character that feels like you.',
        'avatar_empty' => 'No avatar is available right now.', 'identity_title' => 'Your identity',
        'identity_description' => 'Tell us a little about yourself and personalize your profile.',
        'preview_label' => 'Preview', 'display_name' => 'Display name', 'bio' => 'Your bio', 'optional' => '(optional)',
        'affinities_title' => 'Your worlds', 'affinities_description' => 'What you enjoy and how often you visit.',
        'visit_frequency' => 'Visit frequency',
        'frequency_descriptions' => ['rarely' => 'A few times a year', 'sometimes' => 'Several times a year', 'often' => 'Several times a month', 'very_often' => 'Every week'],
        'preview_title' => 'Your preview', 'preview_description' => 'Here is how other members will discover you.',
        'preview_name' => 'Your name', 'visibility' => 'Visible in suggestions',
        'editable' => 'You can change this information at any time.',
    ],
    'stepper' => ['navigation' => 'Profile progress', 'progress' => ':current of :total', 'step' => 'Step :number: :label'],
];
