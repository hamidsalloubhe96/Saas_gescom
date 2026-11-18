<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie des abonnements : essai → actif → en retard (jours de grâce) → expiré (entreprise suspendue),
 * et retour à actif dès qu'un paiement est validé.
 */
class SubscriptionService
{
    /** Démarre l'essai gratuit d'une nouvelle entreprise (durée réglable dans la console). */
    public function startTrial(Tenant $tenant): Subscription
    {
        $plan = Plan::where('is_trial', true)->orderBy('sort')->first()
            ?? Plan::orderBy('sort')->firstOrFail();
        $days = PlatformSetting::int('trial_days', (int) ($plan->trial_days ?: 14));

        return Subscription::updateOrCreate(['tenant_id' => $tenant->id], [
            'plan_id' => $plan->id, 'status' => 'trial', 'trial_ends_at' => now()->addDays($days),
            'current_period_start' => null, 'current_period_end' => null, 'grace_ends_at' => null, 'cancelled_at' => null,
        ]);
    }

    public function for(Tenant $tenant): Subscription
    {
        return Subscription::firstWhere('tenant_id', $tenant->id) ?? $this->startTrial($tenant);
    }

    /** Active (ou prolonge) l'abonnement : $months périodes à partir de la fin en cours (ou de maintenant si échu). */
    public function activate(Tenant $tenant, Plan $plan, int $months = 1): Subscription
    {
        $sub = $this->for($tenant);
        $from = $sub->status === 'active' && $sub->current_period_end?->isFuture() ? $sub->current_period_end : now();
        $months = max(1, $months);

        $sub->update([
            'plan_id' => $plan->id, 'status' => 'active',
            'current_period_start' => $sub->status === 'active' && $sub->current_period_end?->isFuture() ? $sub->current_period_start : now(),
            'current_period_end' => $from->copy()->addMonths($months * max(1, $plan->period_months)),
            'grace_ends_at' => null, 'cancelled_at' => null,
        ]);

        $this->liftSubscriptionSuspension($tenant);

        return $sub->fresh();
    }

    /** Jours offerts : prolonge l'essai ou la période, et rouvre l'accès si l'abonnement était terminé. */
    public function offerDays(Tenant $tenant, int $days): Subscription
    {
        $sub = $this->for($tenant);

        if ($sub->status === 'trial') {
            $sub->update(['trial_ends_at' => max(now(), $sub->trial_ends_at ?? now())->copy()->addDays($days)]);
        } elseif (in_array($sub->status, ['active', 'past_due'], true)) {
            $sub->update(['status' => 'active', 'grace_ends_at' => null,
                'current_period_end' => max(now(), $sub->current_period_end ?? now())->copy()->addDays($days)]);
        } else { // expiré ou résilié : réouverture pour la durée offerte
            $sub->update(['status' => 'active', 'current_period_start' => now(), 'current_period_end' => now()->addDays($days),
                'grace_ends_at' => null, 'cancelled_at' => null]);
        }

        $this->liftSubscriptionSuspension($tenant);

        return $sub->fresh();
    }

    public function changePlan(Tenant $tenant, Plan $plan): Subscription
    {
        $sub = $this->for($tenant);
        $sub->update(['plan_id' => $plan->id]);

        return $sub->fresh();
    }

    public function cancel(Tenant $tenant): Subscription
    {
        $sub = $this->for($tenant);
        $sub->update(['status' => 'cancelled', 'cancelled_at' => now(), 'grace_ends_at' => null]);
        $this->suspendBySubscription($tenant, 'Abonnement résilié');

        return $sub->fresh();
    }

    /** Enregistre un paiement déclaré par l'entreprise : il attend la validation de l'équipe. */
    public function declarePayment(Tenant $tenant, array $data): SubscriptionPayment
    {
        $sub = $this->for($tenant);
        $plan = isset($data['plan_id']) ? Plan::where('is_active', true)->where('is_trial', false)->findOrFail($data['plan_id']) : $sub->plan;

        return SubscriptionPayment::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $sub->id, 'plan_id' => $plan->id,
            'amount' => $data['amount'], 'currency' => $plan->currency, 'months' => $data['months'] ?? 1,
            'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'status' => 'pending',
            'source' => 'tenant', 'paid_at' => $data['paid_at'] ?? now()->toDateString(),
        ]);
    }

    /** Valide un paiement (déclaré ou encaissé hors ligne) : prolonge l'abonnement et rouvre l'accès. */
    public function validatePayment(SubscriptionPayment $payment, ?PlatformAdmin $by = null, string $source = 'console'): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $by, $source) {
            $payment = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_if($payment->status !== 'pending', 422, 'Ce paiement a déjà été traité.');

            $tenant = Tenant::findOrFail($payment->tenant_id);
            $plan = $payment->plan ?? $this->for($tenant)->plan;

            $payment->update(['status' => 'validated', 'validated_by' => $by?->id, 'validated_at' => now(), 'source' => $payment->source ?: $source]);
            $this->activate($tenant, $plan, $payment->months);

            return $payment->fresh();
        });
    }

    public function rejectPayment(SubscriptionPayment $payment, ?PlatformAdmin $by, ?string $note): SubscriptionPayment
    {
        abort_if($payment->status !== 'pending', 422, 'Ce paiement a déjà été traité.');
        $payment->update(['status' => 'rejected', 'validated_by' => $by?->id, 'validated_at' => now(), 'note' => $note]);

        return $payment->fresh();
    }

    /** Encaissement hors ligne saisi par l'équipe : créé et validé en une seule opération. */
    public function recordOfflinePayment(Tenant $tenant, Plan $plan, array $data, PlatformAdmin $by): SubscriptionPayment
    {
        $sub = $this->for($tenant);
        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $sub->id, 'plan_id' => $plan->id,
            'amount' => $data['amount'], 'currency' => $plan->currency, 'months' => $data['months'] ?? 1,
            'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'status' => 'pending',
            'source' => 'console', 'paid_at' => $data['paid_at'] ?? now()->toDateString(), 'note' => $data['note'] ?? null,
        ]);

        return $this->validatePayment($payment, $by, 'console');
    }

    /** Suspension automatique (jamais par-dessus une suspension décidée par la console). */
    public function suspendBySubscription(Tenant $tenant, string $reason): void
    {
        if ($tenant->status !== Tenant::STATUS_ACTIVE) {
            return;
        }
        $tenant->forceFill([
            'status' => Tenant::STATUS_SUSPENDED, 'suspended_at' => now(), 'suspended_reason' => $reason, 'suspension_source' => 'subscription',
        ])->save();
    }

    /** Un paiement ou des jours offerts rouvrent l'accès, mais seulement si c'est l'abonnement qui l'avait fermé. */
    public function liftSubscriptionSuspension(Tenant $tenant): void
    {
        $tenant->refresh();
        if ($tenant->status === Tenant::STATUS_SUSPENDED && $tenant->suspension_source === 'subscription') {
            $tenant->forceFill([
                'status' => Tenant::STATUS_ACTIVE, 'suspended_at' => null, 'suspended_reason' => null, 'suspension_source' => null,
                'deletion_scheduled_at' => null, // une entreprise qui règle redevient cliente : plus de suppression programmée
            ])->save();
        }
    }

    /**
     * Tâche quotidienne : fait avancer les états selon les dates.
     *
     * @return array{past_due: int, expired: int}
     */
    public function refresh(): array
    {
        $grace = PlatformSetting::int('grace_days', 7);
        $out = ['past_due' => 0, 'expired' => 0];

        // Essai terminé -> expiré + suspension.
        foreach (Subscription::where('status', 'trial')->where('trial_ends_at', '<', now())->get() as $sub) {
            $sub->update(['status' => 'expired']);
            $this->suspendBySubscription($sub->tenant, 'Essai gratuit terminé');
            $out['expired']++;
            AuditLog::record('subscription.expired', "Essai terminé pour {$sub->tenant_id}", $sub->tenant_id, [], null);
        }

        // Période payée échue -> en retard (jours de grâce).
        foreach (Subscription::where('status', 'active')->where('current_period_end', '<', now())->get() as $sub) {
            $sub->update(['status' => 'past_due', 'grace_ends_at' => $sub->current_period_end->copy()->addDays($grace)]);
            $out['past_due']++;
            AuditLog::record('subscription.past_due', "Abonnement en retard : {$sub->tenant_id}", $sub->tenant_id, [], null);
        }

        // Jours de grâce écoulés -> expiré + suspension.
        foreach (Subscription::where('status', 'past_due')->where('grace_ends_at', '<', now())->get() as $sub) {
            $sub->update(['status' => 'expired']);
            $this->suspendBySubscription($sub->tenant, 'Abonnement impayé');
            $out['expired']++;
            AuditLog::record('subscription.expired', "Abonnement expiré : {$sub->tenant_id}", $sub->tenant_id, [], null);
        }

        return $out;
    }
}
