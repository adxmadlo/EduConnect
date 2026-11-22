<?php

/**
 * Messages de validation en français.
 * Les règles non listées retombent sur l'anglais de Laravel : compléter au besoin.
 */
return [
    'required'    => 'Le champ :attribute est obligatoire.',
    'email'       => 'Le champ :attribute doit être une adresse e-mail valide.',
    'string'      => 'Le champ :attribute doit être un texte.',
    'numeric'     => 'Le champ :attribute doit être un nombre.',
    'integer'     => 'Le champ :attribute doit être un entier.',
    'boolean'     => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed'   => 'La confirmation du champ :attribute ne correspond pas.',
    'unique'      => 'Cette valeur de :attribute est déjà utilisée.',
    'exists'      => 'La valeur sélectionnée pour :attribute est invalide.',
    'in'          => 'La valeur sélectionnée pour :attribute est invalide.',
    'date'        => 'Le champ :attribute n’est pas une date valide.',
    'min' => [
        'numeric' => 'Le champ :attribute doit être au minimum :min.',
        'string'  => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'max' => [
        'numeric' => 'Le champ :attribute ne peut pas dépasser :max.',
        'string'  => 'Le champ :attribute ne peut pas dépasser :max caractères.',
    ],
    'password' => [
        'letters' => 'Le mot de passe doit contenir au moins une lettre.',
        'mixed'   => 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        'symbols' => 'Le mot de passe doit contenir au moins un caractère spécial.',
        'uncompromised' => 'Ce mot de passe est apparu dans une fuite de données : choisissez-en un autre.',
    ],

    'attributes' => [
        'email'                => 'adresse e-mail',
        'password'             => 'mot de passe',
        'telephone'            => 'téléphone',
        'adresse'              => 'adresse',
        'statut'               => 'statut',
        'note_devoir'          => 'note de devoir',
        'note_examen'          => 'note d’examen',
        'nouveau_mot_de_passe' => 'nouveau mot de passe',
        'mot_de_passe_actuel'  => 'mot de passe actuel',
    ],
];
