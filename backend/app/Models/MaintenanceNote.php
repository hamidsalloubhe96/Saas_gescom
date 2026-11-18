<?php

namespace App\Models;

class MaintenanceNote extends CentralModel
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'done_at', 'done_by'];

    /** Rappels : clé => [libellé, fréquence en jours]. */
    public const REMINDERS = [
        'restore_test' => ['Tester une restauration depuis une sauvegarde', 90],
        'monthly' => ['Maintenance mensuelle (mises à jour des dépendances, revue des journaux)', 31],
        'quarterly' => ['Maintenance trimestrielle (revue des accès, mots de passe, comptes inactifs)', 92],
    ];

    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }
}
