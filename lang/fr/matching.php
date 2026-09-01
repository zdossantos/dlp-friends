<?php

return [
    'meta' => [
        'title' => 'Comment fonctionne le matching de DLP Friends',
        'description' => 'Découvre comment DLP Friends sélectionne et classe les profils suggérés, puis crée un Univers croisé après deux Découvertes réciproques.',
    ],
    'eyebrow' => 'Un algorithme expliqué simplement',
    'title' => 'Comment fonctionnent les suggestions',
    'introduction' => 'Une suggestion, une Découverte et un Univers croisé correspondent à trois étapes différentes. Voici les règles précises appliquées aujourd’hui.',
    'toc_label' => 'Sur cette page',
    'sections' => [
        [
            'id' => 'eligibilite',
            'title' => '1. Qui peut apparaître dans tes suggestions ?',
            'paragraphs' => [
                'Avant tout classement, DLP Friends vérifie qu’un profil peut réellement t’être proposé.',
            ],
            'items' => [
                'Le compte doit être actif et appartenir à une personne majeure.',
                'Le profil doit être complet, visible et utiliser un avatar encore actif.',
                'Ton propre profil et les profils que tu as déjà évalués sont exclus.',
                'Une personne que tu as bloquée, ou qui t’a bloqué, ne peut pas apparaître.',
            ],
        ],
        [
            'id' => 'classement',
            'title' => '2. Comment les profils sont-ils classés ?',
            'paragraphs' => [
                'Les profils éligibles sont ordonnés selon trois règles appliquées dans cet ordre.',
            ],
            'items' => [
                'Le nombre d’univers favoris actifs en commun passe en premier.',
                'À nombre égal, un bonus de 0,25 s’applique lorsque vous avez renseigné la même fréquence de visite.',
                'Si ces deux critères sont encore identiques, un départage aléatoire détermine l’ordre.',
            ],
            'note' => 'L’âge sert uniquement à vérifier la majorité. Il ne filtre pas et ne classe jamais les suggestions.',
        ],
        [
            'id' => 'reciprocite',
            'title' => '3. De la suggestion à l’Univers croisé',
            'paragraphs' => [
                'Une suggestion est seulement un profil affiché dans Explorer. Passer ou choisir Découvrir enregistre ensuite ton choix.',
                'Un Univers croisé est créé uniquement après deux Découvertes réciproques. Un seul choix Découvrir ne suffit jamais et n’ouvre aucun échange.',
            ],
            'items' => [],
            'note' => 'Deux Découvertes créent un Univers croisé et permettent de commencer un échange privé.',
        ],
        [
            'id' => 'criteres-absents',
            'title' => '4. Ce que le classement n’utilise pas',
            'paragraphs' => [
                'DLP Friends ne demande aucune ville et ne classe pas les membres selon une distance, une tranche d’âge ou un critère romantique.',
                'Ces explications décrivent le moteur actuellement utilisé. Elles seront mises à jour avec ses tests si ses règles évoluent.',
            ],
            'items' => [],
        ],
    ],
    'back' => 'Revenir à l’accueil',
    'footer' => 'DLP Friends est réservé aux adultes, indépendant et non affilié à Disney ou Disneyland Paris.',
];
