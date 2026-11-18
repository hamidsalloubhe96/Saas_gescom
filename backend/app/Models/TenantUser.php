<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Entrée de l'annuaire central (base commune), jamais une table d'entreprise. */
class TenantUser extends Model
{
    protected $table = 'tenant_users';

    protected $fillable = ['tenant_id', 'user_id', 'name', 'email', 'role', 'is_active', 'last_login_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_login_at' => 'datetime'];
    }

    /** Toujours la base commune, même quand le code s'exécute dans le contexte d'une entreprise. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
