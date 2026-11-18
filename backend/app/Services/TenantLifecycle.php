<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use Throwable;

/**
 * Fin de vie d'une entreprise : suppression programmée avec délai de grâce (un export est pris d'abord),
 * annulation possible jusqu'à la date, protection contre le nettoyage automatique, purge des échues.
 */
class TenantLifecycle
{
    public function __construct(private TenantExporter $exporter) {}

    public function graceDays(): int
    {
        return PlatformSetting::int('deletion_grace_days', 30);
    }

    /**
     * Programme la suppression. L'export est OBLIGATOIRE : sans lui on ne programme rien.
     *
     * @param  bool  $block  true = l'entreprise est immédiatement fermée (suppression décidée à la main)
     */
    public function schedule(Tenant $tenant, string $reason, bool $block = true): \Illuminate\Support\Carbon
    {
        abort_if($tenant->is_protected, 422, 'Cette entreprise est protégée : retirez la protection avant de programmer sa suppression.');
        abort_if($tenant->deletion_scheduled_at, 422, 'La suppression de cette entreprise est déjà programmée.');

        $export = $this->exporter->export($tenant); // lève une exception si pg_dump échoue
        $date = now()->addDays($this->graceDays());

        $attrs = ['deletion_scheduled_at' => $date];
        if ($block) {
            $attrs += ['status' => Tenant::STATUS_PENDING_DELETION, 'suspended_at' => now(), 'suspended_reason' => $reason, 'suspension_source' => 'console'];
        }
        $tenant->forceFill($attrs)->save();

        AuditLog::record('tenant.schedule_deletion', "Suppression de « {$tenant->name} » programmée le {$date->format('d/m/Y')} ({$reason})", $tenant->id, ['export' => $export]);

        return $date;
    }

    /** Annule la suppression ; si elle avait fermé l'entreprise, l'accès est rétabli. */
    public function cancel(Tenant $tenant): void
    {
        $attrs = ['deletion_scheduled_at' => null];
        if ($tenant->status === Tenant::STATUS_PENDING_DELETION) {
            $attrs += ['status' => Tenant::STATUS_ACTIVE, 'suspended_at' => null, 'suspended_reason' => null, 'suspension_source' => null];
        }
        $tenant->forceFill($attrs)->save();

        AuditLog::record('tenant.cancel_deletion', "Suppression de « {$tenant->name} » annulée", $tenant->id);
    }

    public function setProtected(Tenant $tenant, bool $protected): void
    {
        $tenant->forceFill(['is_protected' => $protected] + ($protected ? ['deletion_scheduled_at' => null] : []))->save();
        AuditLog::record($protected ? 'tenant.protect' : 'tenant.unprotect', "« {$tenant->name} » ".($protected ? 'protégée contre la suppression automatique' : 'n\'est plus protégée'), $tenant->id);
    }

    /**
     * Supprime définitivement les entreprises dont la date est échue (schéma PostgreSQL supprimé).
     * Jamais une entreprise protégée, ni une entreprise redevenue active entre-temps.
     *
     * @return array{purged: int, failed: int}
     */
    public function purgeDue(): array
    {
        $out = ['purged' => 0, 'failed' => 0];

        foreach (Tenant::whereNotNull('deletion_scheduled_at')->where('deletion_scheduled_at', '<=', now())->where('is_protected', false)->where('status', '!=', Tenant::STATUS_ACTIVE)->get() as $tenant) {
            try {
                $name = $tenant->name;
                $id = $tenant->id;
                $tenant->delete(); // l'événement TenantDeleted supprime le schéma ; abonnement et annuaire sont nettoyés (Tenant::booted)
                AuditLog::record('tenant.purged', "Entreprise « {$name} » supprimée définitivement après le délai de grâce", $id, [], null);
                $out['purged']++;
            } catch (Throwable $e) {
                report($e);
                $out['failed']++;
            }
        }

        return $out;
    }

    /**
     * Entreprises abandonnées : abonnement terminé (expiré / résilié), suspendues pour ça, sans connexion depuis N jours.
     * On PROGRAMME leur suppression avec délai de grâce (elles restent libres de payer pour annuler). Jamais les protégées.
     *
     * @return array{flagged: int, failed: int}
     */
    public function flagAbandoned(): array
    {
        $out = ['flagged' => 0, 'failed' => 0];
        $limit = now()->subDays(PlatformSetting::int('abandoned_days', 90));

        $candidates = Tenant::where('is_protected', false)->whereNull('deletion_scheduled_at')
            ->where('status', Tenant::STATUS_SUSPENDED)->where('suspension_source', 'subscription')
            ->whereHas('subscription', fn ($s) => $s->whereIn('status', ['expired', 'cancelled']))->get();

        foreach ($candidates as $tenant) {
            $lastSeen = $tenant->last_activity_at ?? $tenant->created_at;
            if ($lastSeen && $lastSeen->greaterThan($limit)) {
                continue;
            }
            try {
                $this->schedule($tenant, 'Entreprise abandonnée', block: false);
                $out['flagged']++;
            } catch (Throwable $e) {
                report($e);
                $out['failed']++;
            }
        }

        return $out;
    }
}
