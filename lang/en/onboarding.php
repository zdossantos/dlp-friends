<?php

return [
    'invalid_transition' => 'This tutorial step is not available yet.',
    'avatar_in_use' => 'This avatar is used by the tutorial. Replace it in the configuration before continuing.',
    'configuration_saved' => 'Tutorial configuration saved.',
    'unavailable' => 'The tutorial is temporarily unavailable.',
    'page_title' => 'Getting started',
    'initial_message' => 'Hi! What is your favorite place in the park?',
    'steps' => [
        'avatar' => 'Avatar', 'identity' => 'Identity', 'affinities' => 'Worlds', 'preview' => 'Preview',
        'pass' => 'Pass', 'discover' => 'Discover', 'crossed_worlds' => 'Crossed worlds', 'conversation' => 'Chat', 'install_app' => 'App',
    ],
    'instructions' => [
        'pass' => 'To learn how to dismiss a profile, choose Pass.',
        'discover' => 'Choose Discover to show that you would like to get acquainted.',
        'crossed_worlds' => 'When two members choose Discover, their worlds cross.',
        'conversation' => 'Send a first message to continue.',
        'install_app' => 'Install the app for the complete mobile experience.',
        'reject' => 'Pass on this profile to continue.',
        'discover_required' => 'Discover this profile to continue.',
    ],
    'install' => [
        'title' => 'DLP Friends, just like a real app',
        'description' => 'Add DLP Friends to your Home Screen to open it full screen and get back to your chats faster.',
        'action' => 'Install the app',
        'installed' => 'The app is installed on this device.',
        'ios_title' => 'Install on iPhone or iPad',
        'ios_instructions' => 'In Safari, tap Share, then “Add to Home Screen” and confirm by tapping Add.',
        'unavailable' => 'This browser does not offer automatic installation. You can continue and use DLP Friends normally.',
        'notifications_preview' => 'Once the app is installed, you can choose to enable notifications for messages and other updates.',
        'notifications_title' => 'Stay updated, even when the app is closed',
        'notifications_action' => 'Enable notifications',
        'notifications_enabled' => 'Notifications enabled',
        'notifications_denied' => 'Notifications are blocked in system settings. You can allow them later.',
        'finish' => 'Finish',
        'skip' => 'Skip this step',
        'skip_confirmation' => 'Without installation or notifications, you will not receive alerts while DLP Friends is closed. Do you want to continue?',
    ],
    'errors' => [
        'step' => 'This step could not be completed. Please try again.',
        'message' => 'Your message could not be sent. Please try again.',
    ],
    'message_history' => 'Message history',
    'demo_profiles' => [
        'pass' => ['interests' => ['Shows', 'Photography']],
        'like' => ['interests' => ['Attractions', 'Restaurants']],
    ],
];
