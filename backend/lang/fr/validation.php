<?php

return [
    'required' => 'Ce champ est obligatoire.',
    'email' => 'Adresse email invalide.',
    'unique' => 'Cette valeur est déjà utilisée.',
    'confirmed' => 'La confirmation ne correspond pas.',
    'string' => 'Valeur invalide.',
    'boolean' => 'Valeur invalide.',
    'numeric' => 'Doit être un nombre.',
    'integer' => 'Doit être un nombre entier.',
    'regex' => 'Format invalide.',
    'not_in' => 'Cette valeur n\'est pas autorisée.',
    'in' => 'Valeur invalide.',
    'date' => 'Date invalide.',
    'array' => 'Format invalide.',
    'after_or_equal' => "La date doit être postérieure ou égale à la date d'émission.",
    'exists' => 'Valeur introuvable.',
    'gt' => [
        'numeric' => 'Doit être supérieur à :value.',
    ],
    'max' => [
        'string' => 'Maximum :max caractères.',
        'numeric' => 'Doit être inférieur ou égal à :max.',
    ],
    'min' => [
        'string' => 'Minimum :min caractères.',
        'numeric' => 'Doit être supérieur ou égal à :min.',
    ],
    'attributes' => [],
];
