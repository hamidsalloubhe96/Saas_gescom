<?php

namespace App\Observers;

use App\Models\TenantUser;
use App\Models\User;

/**
 * Tient l'annuaire central à jour : chaque création, modification ou suppression d'un utilisateur
 * d'entreprise est recopiée dans `tenant_users`. Sans effet hors contexte d'entreprise.
 */
class UserDirectoryObserver
{
    public function saved(User $user): void
    {
        if (! tenancy()->initialized || ! tenant()) {
            return;
        }

        TenantUser::updateOrCreate(
            ['tenant_id' => tenant('id'), 'user_id' => $user->id],
            ['name' => $user->name, 'email' => strtolower($user->email), 'role' => $user->role, 'is_active' => (bool) $user->is_active],
        );
    }

    public function deleted(User $user): void
    {
        if (tenancy()->initialized && tenant()) {
            TenantUser::where('tenant_id', tenant('id'))->where('user_id', $user->id)->delete();
        }
    }
}
