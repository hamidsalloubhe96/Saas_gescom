<?php

/*
| Catalogue des droits. Un utilisateur a UN rôle ; le rôle donne une liste de permissions.
| Les permissions sont vérifiées côté serveur à chaque requête (middleware "permission:xxx"),
| le frontend ne fait qu'adapter l'affichage.
*/

$permissions = [
    'dashboard.view' => 'Voir le tableau de bord',
    'customers.view' => 'Consulter les clients',
    'customers.manage' => 'Créer, modifier, supprimer les clients',
    'suppliers.view' => 'Consulter les fournisseurs',
    'suppliers.manage' => 'Créer, modifier, supprimer les fournisseurs',
    'products.view' => 'Consulter les produits et le stock',
    'products.manage' => 'Gérer les produits et les catégories',
    'stock.move' => 'Saisir des mouvements de stock (entrée, sortie, inventaire)',
    'sales.view' => 'Consulter les devis et factures',
    'sales.manage' => 'Créer et gérer devis et factures',
    'sales.pay' => 'Enregistrer les paiements clients',
    'purchases.view' => 'Consulter les commandes fournisseurs',
    'purchases.manage' => 'Créer et gérer les commandes fournisseurs',
    'purchases.receive' => 'Réceptionner les marchandises',
    'purchases.pay' => 'Enregistrer les règlements fournisseurs',
    'pos.use' => 'Utiliser la caisse (ouvrir, vendre, clôturer)',
    'pos.void' => 'Annuler une vente en caisse',
    'users.manage' => 'Gérer les utilisateurs et leurs rôles',
    'branches.manage' => 'Gérer les points de vente, leurs prix et leur équipe',
    'transfers.manage' => 'Créer et expédier les transferts de stock entre points de vente',
    'transfers.receive' => 'Demander et réceptionner des transferts de stock',
    'production.manage' => 'Gérer la production (recettes, fournées) et le bilan des invendus',
    'billing.manage' => 'Gérer l\'abonnement et déclarer les paiements à la plateforme',
];

$all = array_keys($permissions);

return [
    'permissions' => $permissions,

    'roles' => [
        'admin' => [
            'label' => 'Administrateur',
            'description' => 'Accès complet, y compris la gestion des utilisateurs.',
            'permissions' => ['*'],
        ],
        'manager' => [
            'label' => 'Gérant',
            'description' => 'Pilote toute l\'activité commerciale, sans gérer les utilisateurs.',
            'permissions' => array_values(array_diff($all, ['users.manage'])),
        ],
        'seller' => [
            'label' => 'Vendeur',
            'description' => 'Clients, devis, factures et encaissements ; consulte les produits.',
            'permissions' => [
                'customers.view', 'customers.manage', 'products.view',
                'sales.view', 'sales.manage', 'sales.pay', 'pos.use',
            ],
        ],
        'cashier' => [
            'label' => 'Caissier',
            'description' => 'Utilise uniquement la caisse (sans annulation de vente).',
            'permissions' => ['products.view', 'customers.view', 'pos.use'],
        ],
        'storekeeper' => [
            'label' => 'Magasinier',
            'description' => 'Produits, mouvements de stock et réception des commandes.',
            'permissions' => [
                'products.view', 'products.manage', 'stock.move',
                'suppliers.view', 'purchases.view', 'purchases.receive', 'transfers.manage', 'transfers.receive', 'production.manage',
            ],
        ],
        'accountant' => [
            'label' => 'Comptable',
            'description' => 'Consulte tout, enregistre les paiements clients et fournisseurs.',
            'permissions' => [
                'dashboard.view', 'customers.view', 'suppliers.view', 'products.view',
                'sales.view', 'sales.pay', 'purchases.view', 'purchases.pay',
            ],
        ],
    ],
];
