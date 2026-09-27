<?php

return [
    'invalid_transition' => 'Cette étape du tutoriel n’est pas encore disponible.',
    'avatar_in_use' => 'Cet avatar est utilisé par le tutoriel. Remplacez-le dans la configuration avant de continuer.',
    'configuration_saved' => 'Configuration du tutoriel enregistrée.',
    'unavailable' => 'Le tutoriel est temporairement indisponible.',
    'page_title' => 'Prise en main',
    'initial_message' => 'Bonjour ! Quel est ton endroit préféré dans le parc ?',
    'steps' => [
        'avatar' => 'Avatar', 'identity' => 'Identité', 'affinities' => 'Univers', 'preview' => 'Aperçu',
        'pass' => 'Passer', 'discover' => 'Découvrir', 'crossed_worlds' => 'Univers croisés', 'conversation' => 'Échange', 'install_app' => 'Application',
    ],
    'instructions' => [
        'pass' => 'Pour découvrir comment écarter un profil, choisis Passer.',
        'discover' => 'Choisis Découvrir pour indiquer que tu souhaites faire connaissance.',
        'crossed_worlds' => 'Lorsque deux membres choisissent Découvrir, leurs univers se croisent.',
        'conversation' => 'Envoie un premier message pour continuer.',
        'install_app' => 'Installe l’application pour une expérience mobile complète.',
        'reject' => 'Passe ce profil pour continuer.',
        'discover_required' => 'Découvre ce profil pour continuer.',
    ],
    'install' => [
        'title' => 'DLP Friends, comme une vraie app',
        'description' => 'Ajoute DLP Friends à ton écran d’accueil pour l’ouvrir en plein écran et retrouver plus vite tes échanges.',
        'action' => 'Installer l’application',
        'installed' => 'L’application est installée sur cet appareil.',
        'ios_title' => 'Installation sur iPhone ou iPad',
        'ios_instructions' => 'Dans Safari, touche Partager, puis « Sur l’écran d’accueil » et confirme avec Ajouter.',
        'unavailable' => 'L’installation automatique n’est pas proposée par ce navigateur. Tu peux continuer et utiliser DLP Friends normalement.',
        'notifications_preview' => 'Une fois l’application installée, tu pourras choisir d’activer les notifications pour tes messages et les autres nouveautés.',
        'notifications_title' => 'Reste au courant, même app fermée',
        'notifications_action' => 'Activer les notifications',
        'notifications_enabled' => 'Notifications activées',
        'notifications_denied' => 'Les notifications sont bloquées dans les réglages système. Tu pourras les autoriser plus tard.',
        'finish' => 'Terminer',
        'skip' => 'Passer cette étape',
        'skip_confirmation' => 'Sans installation ni notifications, tu ne recevras pas d’alerte lorsque DLP Friends est fermé. Veux-tu continuer ?',
    ],
    'errors' => [
        'step' => 'Cette étape n’a pas pu être validée. Réessaie.',
        'message' => 'Ton message n’a pas pu être envoyé. Réessaie.',
    ],
    'message_history' => 'Historique des messages',
    'demo_profiles' => [
        'pass' => ['interests' => ['Spectacles', 'Photographie']],
        'like' => ['interests' => ['Attractions', 'Restaurants']],
    ],
];
