<?php

return [
    'navigation' => 'Notifications',
    'page' => [
        'title' => 'Notifications',
        'description' => 'Find updates from your conversations and events.',
        'empty_title' => 'No notifications',
        'empty_description' => 'New updates will appear here.',
        'list_label' => 'Notification list',
        'pagination' => 'Notification pagination',
    ],
    'filters' => [
        'label' => 'Filter notifications',
        'all' => 'All',
        'conversations' => 'Conversations',
        'events' => 'Events',
        'unread_only' => 'Unread only',
    ],
    'actions' => ['mark_all_read' => 'Mark all as read'],
    'items' => [
        'new_match' => 'You can now chat with :member.',
        'new_message' => ':sender sent you a new message.',
        'event_accepted' => 'Your registration for “:event” was accepted.',
        'event_refused' => 'Your request for “:event” was refused.',
        'event_removed' => 'You are no longer attending “:event”.',
        'event_changed' => 'The date or location of “:event” changed.',
        'event_cancelled' => '“:event” was cancelled.',
        'unread' => 'Unread',
    ],
    'accessibility' => ['unread_count' => ':count unread notifications'],
];
