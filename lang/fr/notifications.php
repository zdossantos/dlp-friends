<?php

return [
    'navigation' => 'Notifications',
    'page' => [
        'title' => 'Notifications',
        'description' => 'Retrouve les nouveautés de tes conversations et événements.',
        'empty_title' => 'Aucune notification',
        'empty_description' => 'Les nouveautés apparaîtront ici.',
        'list_label' => 'Liste des notifications',
        'pagination' => 'Pagination des notifications',
    ],
    'filters' => [
        'label' => 'Filtrer les notifications',
        'all' => 'Toutes',
        'conversations' => 'Conversations',
        'events' => 'Événements',
        'unread_only' => 'Non lues uniquement',
    ],
    'actions' => ['mark_all_read' => 'Tout marquer comme lu'],
    'items' => [
        'new_match' => 'Tu peux maintenant discuter avec :member.',
        'new_message' => ':sender t’a envoyé un nouveau message.',
        'unread' => 'Non lue',
    ],
    'accessibility' => ['unread_count' => ':count notifications non lues'],
];
