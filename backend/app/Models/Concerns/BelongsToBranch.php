<?php

namespace App\Models\Concerns;

use App\Services\CurrentBranch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Limite automatiquement les requêtes au point de vente courant (s'il est imposé ou choisi)
 * et renseigne branch_id à la création. Aucun effet sans utilisateur connecté.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $query) {
            if ($id = app(CurrentBranch::class)->id()) {
                $query->where($query->getModel()->getTable().'.branch_id', $id);
            }
        });

        static::creating(function ($model) {
            if (empty($model->branch_id)) {
                $model->branch_id = app(CurrentBranch::class)->forWrite();
            }
        });
    }
}
