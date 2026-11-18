<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle de la base COMMUNE. Même quand le code s'exécute dans le contexte d'une entreprise
 * (connexion par défaut = schéma de l'entreprise), ces modèles lisent toujours la base commune.
 */
abstract class CentralModel extends Model
{
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }
}
