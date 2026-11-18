<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Actions d'abonnement depuis la fiche d'une entreprise. */
class TenantBillingController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function activate(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_trial', false)],
            'months' => ['required', 'integer', 'min:1', 'max:24'],
        ]);
        $plan = Plan::findOrFail($data['plan_id']);
        $sub = $this->subscriptions->activate($tenant, $plan, (int) $data['months']);

        AuditLog::record('subscription.activate', "Abonnement « {$plan->name} » activé à la main pour {$data['months']} période(s) : {$tenant->name}",
            $tenant->id, ['plan' => $plan->id, 'jusqu_au' => $sub->current_period_end?->toDateString()]);

        return back()->with('success', 'Abonnement activé. Il court jusqu\'au '.$sub->current_period_end->format('d/m/Y').'.');
    }

    public function offerDays(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365']]);
        $sub = $this->subscriptions->offerDays($tenant, (int) $data['days']);

        AuditLog::record('subscription.offer_days', "{$data['days']} jour(s) offert(s) à {$tenant->name}", $tenant->id, ['days' => $data['days']]);

        return back()->with('success', "{$data['days']} jour(s) offert(s). Fin : ".$sub->endsAt()->format('d/m/Y').'.');
    }

    public function changePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        $plan = Plan::findOrFail($data['plan_id']);
        $this->subscriptions->changePlan($tenant, $plan);

        AuditLog::record('subscription.change_plan', "Forfait changé pour « {$plan->name} » : {$tenant->name}", $tenant->id, ['plan' => $plan->id]);

        return back()->with('success', 'Forfait modifié.');
    }

    public function cancel(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:'.$tenant->id]], ['confirm.in' => 'Saisissez l\'identifiant exact de l\'entreprise pour confirmer.', 'confirm.required' => 'Saisissez l\'identifiant de l\'entreprise pour confirmer.']);
        $this->subscriptions->cancel($tenant);

        AuditLog::record('subscription.cancel', "Abonnement résilié : {$tenant->name}", $tenant->id);

        return back()->with('success', 'Abonnement résilié : l\'accès de l\'entreprise est fermé jusqu\'à un nouveau paiement.');
    }

    /** Paiement reçu hors ligne (espèces, virement, mobile money) : créé et validé en une fois. */
    public function recordPayment(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_trial', false)],
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'method' => ['required', Rule::in(array_keys(SubscriptionPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:190'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = $this->subscriptions->recordOfflinePayment($tenant, Plan::findOrFail($data['plan_id']), $data, auth('admin')->user());

        AuditLog::record('payment.record', "Paiement hors ligne de {$payment->amount} {$payment->currency} encaissé pour {$tenant->name} ({$payment->methodLabel()})",
            $tenant->id, ['payment' => $payment->id, 'reference' => $payment->reference]);

        return back()->with('success', 'Paiement encaissé : abonnement prolongé.');
    }
}
