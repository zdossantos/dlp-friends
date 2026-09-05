<?php

return [
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'max' => [
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'before_or_equal' => 'La valeur de :attribute doit être antérieure ou égale au :date.',
    'custom' => [
        'email' => [
            'required' => 'L’adresse e-mail est obligatoire.',
        ],
    ],
    'attributes' => [
        'email' => 'adresse e-mail',
        'birth_date' => 'date de naissance',
        'password' => 'mot de passe',
        'pass_display_name' => 'nom français du profil à passer',
        'pass_display_name_en' => 'nom anglais du profil à passer',
        'pass_bio' => 'biographie française du profil à passer',
        'pass_bio_en' => 'biographie anglaise du profil à passer',
        'like_display_name' => 'nom français du profil à découvrir',
        'like_display_name_en' => 'nom anglais du profil à découvrir',
        'like_bio' => 'biographie française du profil à découvrir',
        'like_bio_en' => 'biographie anglaise du profil à découvrir',
    ],
];
