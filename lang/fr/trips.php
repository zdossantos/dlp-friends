<?php

return [
    'validation' => [
        'local_time' => 'Cet horaire local n’existe pas à Paris.',
        'future' => 'Le départ doit être dans le futur.',
        'horizon' => 'Le départ doit avoir lieu dans les douze prochains mois.',
        'confirm_members' => 'Confirme le partage avec tous les membres.',
        'closed' => 'Ce trajet est déjà parti ou annulé.',
        'locked' => 'Ce champ ne peut plus être modifié après une première acceptation.',
        'capacity' => 'Le nombre de places ne peut pas être inférieur au nombre de participants acceptés.',
    ],
    'attributes' => [
        'transport' => 'transport',
        'direction' => 'sens',
        'general_departure' => 'départ général',
        'destination' => 'destination',
        'departs_at' => 'date de départ',
        'meeting_point' => 'rendez-vous précis',
        'audience' => 'audience',
        'car_seats' => 'places passagers',
    ],
];
