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
        'pass' => 'Passer', 'discover' => 'Découvrir', 'crossed_worlds' => 'Univers croisés', 'conversation' => 'Échange',
    ],
    'instructions' => [
        'pass' => 'Pour découvrir comment écarter un profil, choisis Passer.',
        'discover' => 'Choisis Découvrir pour indiquer que tu souhaites faire connaissance.',
        'crossed_worlds' => 'Lorsque deux membres choisissent Découvrir, leurs univers se croisent.',
        'conversation' => 'Envoie un premier message pour terminer ton inscription.',
        'reject' => 'Passe ce profil pour continuer.',
        'discover_required' => 'Découvre ce profil pour continuer.',
    ],
    'errors' => [
        'step' => 'Cette étape n’a pas pu être validée. Réessaie.',
        'message' => 'Ton message n’a pas pu être envoyé. Réessaie.',
    ],
    'message_history' => 'Historique des messages',
    'demo_profiles' => [
        'pass' => ['display_name' => 'Camille', 'bio' => 'Aime découvrir les détails du parc et profiter des spectacles.', 'interests' => ['Spectacles', 'Photographie']],
        'like' => ['display_name' => 'Alex', 'bio' => 'Toujours partant pour partager une journée conviviale entre fans.', 'interests' => ['Attractions', 'Restaurants']],
    ],
];
