<?php

return [
    'navigation' => 'Notifications',
    'page' => [
        'title' => 'Notifications',
        'description' => 'Retrouve les nouveautés de tes conversations, événements et partenaires.',
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
        'partners' => 'Partenaires',
        'administration' => 'Administration',
        'unread_only' => 'Non lues uniquement',
    ],
    'actions' => [
        'manage_settings' => 'Gérer les notifications',
        'mark_all_read' => 'Tout marquer comme lu',
        'mark_read' => 'Marquer comme lue',
        'read_short' => 'Lue',
        'delete' => 'Supprimer la notification',
        'delete_short' => 'Supprimer',
        'confirm_delete' => 'Supprimer cette notification ?',
        'item_actions' => 'Actions de la notification',
        'open_partner_announcement' => 'Voir l’annonce partenaire',
        'dismiss_partner_announcement' => 'Supprimer cette annonce partenaire',
        'confirm_dismiss_partner_announcement' => 'Supprimer cette annonce partenaire de tes notifications ?',
    ],
    'items' => [
        'new_member' => 'Un nouveau membre a rejoint DLP Friends.',
        'new_match' => 'Nouveau match avec :member.',
        'new_message' => ':sender t’a envoyé un nouveau message.',
        'message_liked' => ':member a aimé ton message.',
        'event_accepted' => 'Ton inscription à « :event » est acceptée.',
        'event_refused' => 'Ta demande pour « :event » a été refusée.',
        'event_removed' => 'Tu ne participes plus à « :event ».',
        'event_changed' => 'La date ou le lieu de « :event » a changé.',
        'event_cancelled' => '« :event » a été annulé.',
        'partner_announcement' => ':announcement',
        'partner_announcement_approved' => 'Ton annonce « :announcement » a été approuvée.',
        'partner_announcement_rejected' => 'Ton annonce « :announcement » a été refusée.',
        'partner_profile_review_requested' => 'La fiche partenaire « :partner » attend ta validation.',
        'partner_announcement_review_requested' => 'L’annonce « :announcement » attend ta validation.',
        'unread' => 'Non lue',
    ],
    'accessibility' => ['unread_count' => ':count notifications non lues'],
    'push' => [
        'new_member' => [
            'title' => 'Nouveau membre',
            'body' => 'Un nouveau membre a rejoint DLP Friends.',
        ],
        'messages' => [
            'title' => 'Nouveau message',
            'body' => 'Un nouveau message t’attend.',
        ],
        'reactions' => [
            'title' => 'Nouvelle réaction',
            'body' => 'Quelqu’un a aimé ton message.',
        ],
        'matches' => [
            'title' => 'Nouveau match',
            'body' => 'Une nouvelle mise en relation amicale t’attend.',
        ],
        'events' => [
            'title' => 'Actualité sur un événement',
            'body' => 'Une information concernant un événement t’attend.',
        ],
        'partner_announcements' => [
            'title' => 'Actualité partenaire',
            'body' => 'Une nouvelle information partenaire est disponible.',
        ],
        'administration' => [
            'title' => 'Action requise',
            'body' => 'Une action d’administration requiert ton attention.',
        ],
    ],
    'admin' => [
        'dispatch_started' => 'La diffusion de l’annonce partenaire a démarré.',
        'retry_started' => 'La reprise des livraisons en échec a démarré.',
        'not_sending' => 'Seule une annonce en cours de diffusion peut être reprise.',
        'not_approved' => 'Seule une annonce approuvée peut être diffusée.',
    ],
];
