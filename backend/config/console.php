<?php

/*
| Droits de la console (équipe de la plateforme). Un rôle = une liste de capacités ("*" = tout).
| Vérifiés côté serveur sur chaque route (middleware "console.can:xxx") ET masqués dans les vues.
*/
return [
    'roles' => [
        'super_admin' => ['*'],

        // Gère les entreprises, abonnements et paiements au quotidien, sans les réglages sensibles ni l'équipe.
        'admin' => [
            'tenants.view', 'tenants.create', 'tenants.update', 'tenants.suspend',
            'users.view', 'users.manage', 'users.impersonate',
            'plans.view', 'plans.manage', 'subscriptions.manage', 'payments.validate',
            'quotas.view', 'quotas.manage', 'requests.manage', 'reviews.manage', 'tickets.manage',
        ],

        // Lecture seule + aide aux utilisateurs (connexion d'assistance en lecture seule, tickets).
        'support' => [
            'tenants.view', 'users.view', 'users.impersonate', 'plans.view', 'quotas.view', 'tickets.manage',
        ],
    ],

    // Libellés (écran « droits de chaque rôle »).
    'abilities' => [
        'tenants.view' => 'Consulter les entreprises',
        'tenants.create' => 'Créer une entreprise',
        'tenants.update' => 'Modifier une entreprise',
        'tenants.suspend' => 'Suspendre et rétablir une entreprise',
        'tenants.ban' => 'Bannir une entreprise',
        'tenants.delete' => 'Programmer la suppression, protéger, exporter',
        'users.view' => 'Rechercher les utilisateurs de toutes les entreprises',
        'users.manage' => 'Désactiver, déconnecter, réinitialiser le mot de passe d\'un utilisateur',
        'users.impersonate' => 'Se connecter en tant qu\'un utilisateur (lecture seule)',
        'users.impersonate.write' => 'Connexion d\'assistance avec droit de modification',
        'plans.view' => 'Consulter les forfaits',
        'plans.manage' => 'Créer et modifier les forfaits',
        'subscriptions.manage' => 'Gérer les abonnements (activer, offrir des jours, résilier)',
        'payments.validate' => 'Valider ou refuser les paiements, encaisser hors ligne',
        'quotas.view' => 'Consulter les limites d\'usage',
        'quotas.manage' => 'Fixer ou débloquer une limite',
        'requests.manage' => 'Traiter les demandes d\'inscription',
        'reviews.manage' => 'Publier ou masquer les avis',
        'tickets.manage' => 'Répondre aux tickets de support',
        'announcements.manage' => 'Publier des annonces',
        'health.view' => 'Santé de l\'application, exports, migrations',
        'audit.view' => 'Consulter le journal d\'activité',
        'team.manage' => 'Gérer l\'équipe de la console',
        'legal.manage' => 'Gérer les textes légaux',
        'settings.manage' => 'Réglages de la plateforme et maintenance',
    ],

    // Sécurité de la connexion.
    'max_login_attempts_per_minute' => 5,
    'impersonation_minutes' => 30,

    // Outils PostgreSQL pour les exports et restaurations par entreprise (dossier ; vide = déjà dans le PATH).
    'pg_bin' => env('PG_BIN_PATH'),

    // Où sont conservés les exports / sauvegardes (disque Laravel). Sur Render le disque du conteneur est
    // ÉPHÉMÈRE : pour conserver les sauvegardes, définir BACKUP_DISK sur un disque externe (ex. s3).
    'backup_disk' => env('BACKUP_DISK', 'local'),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    // Alertes d'erreur par e-mail (laisser vide pour désactiver).
    'alert_email' => env('CONSOLE_ALERT_EMAIL'),

    // Conservation du journal d'activité de la console.
    'audit_retention_days' => 365,
];
