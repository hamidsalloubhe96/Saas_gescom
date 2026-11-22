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

    /** Modules facultatifs, activables quel que soit le type d'activité. */
    public const MODULES = ['expiry' => 'Suivi des dates de péremption', 'production' => 'Fabrication sur place (recettes, production, invendus)'];

    /**
     * Modules actifs. Tant que l'entreprise n'a rien choisi (modules vide), la pharmacie a le suivi des péremptions,
     * la boulangerie la fabrication, et les autres activités n'ont aucun module : le menu reste simple.
     *
     * @return array<int, string>
     */
    public function enabledModules(): array
    {
        if (is_array($this->modules)) {
            return array_values(array_intersect($this->modules, array_keys(self::MODULES)));
        }

        return match ($this->business_type ?? 'general') {
            'pharmacy' => ['expiry'],
            'bakery' => ['production'],
            default => [],
        };
    }

    /**
     * Liste des modules après avoir activé ou désactivé certains d'entre eux ; les autres restent comme ils sont.
     *
     * @param  array<string, bool>  $changes
     * @return array<int, string>
     */
    public function modulesWith(array $changes): array
    {
        $current = $this->enabledModules();
        foreach ($changes as $module => $on) {
            if (! array_key_exists($module, self::MODULES)) {
                continue;
            }
            $current = $on ? array_merge($current, [$module]) : array_diff($current, [$module]);
        }

        return array_values(array_unique($current));
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->enabledModules(), true);
    }

    public static function getCustomColumns(): array
    {
        return [
            'id', 'status', 'suspended_at', 'suspended_reason', 'owner_email', 'country', 'city',
            'last_activity_at', 'suspension_source', 'deletion_scheduled_at', 'is_protected', 'business_type', 'modules', 'created_at', 'updated_at',
        ];
    }

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'deletion_scheduled_at' => 'datetime',
            'is_protected' => 'boolean',
            'modules' => 'array',
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
