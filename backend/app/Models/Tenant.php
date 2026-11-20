<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Entreprise cliente. L'id est son identifiant court (slug), envoyé par le
 * frontend dans l'en-tête X-Tenant. Les attributs listés dans getCustomColumns() sont de vraies
 * colonnes (filtrables par la console) ; les autres (name...) vont dans la colonne JSON "data".
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_BANNED = 'banned';
    public const STATUS_PENDING_DELETION = 'pending_deletion';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_SUSPENDED => 'Suspendue',
        self::STATUS_BANNED => 'Bannie',
        self::STATUS_PENDING_DELETION => 'Suppression programmée',
    ];

    public const BUSINESS_TYPES = ['general' => 'Commerce général', 'hardware' => 'Quincaillerie', 'butcher' => 'Boucherie et produits au poids', 'wholesale' => 'Grossiste et distribution', 'services' => 'Services et prestations', 'pharmacy' => 'Pharmacie', 'bakery' => 'Boulangerie'];

    public static function getCustomColumns(): array
    {
        return [
            'id', 'status', 'suspended_at', 'suspended_reason', 'owner_email', 'country', 'city',
            'last_activity_at', 'suspension_source', 'deletion_scheduled_at', 'is_protected', 'business_type', 'created_at', 'updated_at',
        ];
    }

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'deletion_scheduled_at' => 'datetime',
            'is_protected' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Une entreprise supprimée n'a plus d'abonnement ni d'entrées dans l'annuaire (ses paiements restent, pour la comptabilité).
        static::deleted(function (self $tenant) {
            Subscription::where('tenant_id', $tenant->id)->delete();
            TenantUser::where('tenant_id', $tenant->id)->delete();
        });
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class, 'tenant_id');
    }

    /** L'entreprise peut-elle utiliser l'application ? */
    public function isUsable(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status ?? self::STATUS_ACTIVE] ?? (string) $this->status;
    }
}
