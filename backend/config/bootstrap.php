<?php

/**
 * Premier démarrage : création automatique du premier administrateur de la console et de la première entreprise,
 * sans passer par l'inscription (donc sans e-mail). Lu par `php artisan platform:bootstrap` au démarrage du conteneur.
 * Une fois les comptes créés, retirer ces variables de l'hébergeur.
 */
return [
    'admin_email' => env('BOOTSTRAP_ADMIN_EMAIL'),
    'admin_name' => env('BOOTSTRAP_ADMIN_NAME'),
    'admin_password' => env('BOOTSTRAP_ADMIN_PASSWORD'),

    'company' => env('BOOTSTRAP_COMPANY'),
    'slug' => env('BOOTSTRAP_SLUG'),
    'user_name' => env('BOOTSTRAP_USER_NAME'),
    'user_email' => env('BOOTSTRAP_USER_EMAIL'),
    'user_password' => env('BOOTSTRAP_USER_PASSWORD'),
];
