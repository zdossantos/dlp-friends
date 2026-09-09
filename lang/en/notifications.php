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
        'unread' => 'Unread',
    ],
    'accessibility' => ['unread_count' => ':count unread notifications'],
];
