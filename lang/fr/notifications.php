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
        'unread_only' => 'Non lues uniquement',
    ],
    'actions' => [
        'mark_all_read' => 'Tout marquer comme lu',
        'open_partner_announcement' => 'Voir l’annonce partenaire',
        'dismiss_partner_announcement' => 'Supprimer cette annonce partenaire',
        'confirm_dismiss_partner_announcement' => 'Supprimer cette annonce partenaire de tes notifications ?',
    ],
    'items' => [
        'new_match' => 'Nouveau match avec :member.',
        'new_message' => ':sender t’a envoyé un nouveau message.',
        'event_accepted' => 'Ton inscription à « :event » est acceptée.',
        'event_refused' => 'Ta demande pour « :event » a été refusée.',
        'event_removed' => 'Tu ne participes plus à « :event ».',
        'event_changed' => 'La date ou le lieu de « :event » a changé.',
        'event_cancelled' => '« :event » a été annulé.',
        'partner_announcement' => 'Nouvelle annonce partenaire : « :announcement ».',
        'partner_announcement_approved' => 'Ton annonce « :announcement » a été approuvée.',
        'partner_announcement_rejected' => 'Ton annonce « :announcement » a été refusée.',
        'unread' => 'Non lue',
    ],
    'accessibility' => ['unread_count' => ':count notifications non lues'],
    'admin' => [
        'dispatch_started' => 'La diffusion de l’annonce partenaire a démarré.',
        'retry_started' => 'La reprise des livraisons en échec a démarré.',
        'not_sending' => 'Seule une annonce en cours de diffusion peut être reprise.',
        'not_approved' => 'Seule une annonce approuvée peut être diffusée.',
    ],
];
