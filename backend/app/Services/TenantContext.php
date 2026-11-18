<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Subscription;
use App\Models\Tenant;

/**
 * Informations de la plateforme envoyées au client React avec /login et /me :
 * abonnement et limites, annonces, maintenance… (complété au fil des phases de la console).
 */
class TenantContext
{
    public function __construct(private PlanLimits $limits) {}

    public function forClient(?Tenant $tenant): array
    {
        if (! $tenant) {
            return [];
        }

        return [
            'subscription' => $this->subscription($tenant),
            'announcements' => Announcement::current()->latest('id')->limit(5)->get(['id', 'title', 'body', 'level']),
        ];
    }

    /** Résumé de l'abonnement pour le bandeau et la page « Abonnement ». */
    public function subscription(Tenant $tenant): ?array
    {
        $sub = Subscription::with('plan')->firstWhere('tenant_id', $tenant->id);
        if (! $sub) {
            return null;
        }

        $limits = $this->limits->limitsFor($tenant);
        // En contexte d'entreprise (requête API) on mesure l'usage ; sinon on ne l'envoie pas.
        $usage = tenancy()->initialized ? $this->limits->usageInContext() : null;

        return [
            'status' => $sub->status,
            'status_label' => $sub->statusLabel(),
            'plan' => ['id' => $sub->plan->id, 'name' => $sub->plan->name, 'price' => $sub->plan->price, 'currency' => $sub->plan->currency],
            'ends_at' => $sub->endsAt()?->toIso8601String(),
            'days_left' => $sub->daysLeft(),
            'grace_ends_at' => $sub->grace_ends_at?->toIso8601String(),
            'blocked' => $tenant->status === Tenant::STATUS_SUSPENDED && $tenant->suspension_source === 'subscription',
            'limits' => $limits,
            'usage' => $usage,
            'limits_unlocked' => $sub->limitsUnlocked(),
        ];
    }
}
