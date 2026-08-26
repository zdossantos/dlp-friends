<?php

return [
    'required' => 'Le champ :attribute est obligatoire.',
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
    ],
];
