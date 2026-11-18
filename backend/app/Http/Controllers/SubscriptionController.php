<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Abonnement vu par l'entreprise : état, forfaits proposés, déclaration d'un paiement à valider par la plateforme. */
class SubscriptionController extends Controller
{
    private const MAX_PENDING = 3;

    public function show(TenantContext $context): JsonResponse
    {
        $tenant = tenant();

        return response()->json([
            'subscription' => $context->subscription($tenant),
            'plans' => Plan::where('is_active', true)->where('is_trial', false)->orderBy('sort')->get(),
            'payments' => SubscriptionPayment::with('plan:id,name')->where('tenant_id', $tenant->id)->latest('id')->limit(20)->get(),
            'instructions' => PlatformSetting::get('payment_instructions'),
            'methods' => SubscriptionPayment::METHODS,
        ]);
    }

    public function receipt(int $paymentId): JsonResponse
    {
        $payment = SubscriptionPayment::where('tenant_id', tenant()->id)->findOrFail($paymentId);

        return response()->json($payment->receipt());
    }

    public function declare(Request $request, SubscriptionService $subscriptions): JsonResponse
    {
        $tenant = tenant();

        $data = $request->validate([
            // La table « plans » est dans la base COMMUNE, pas dans le schéma de l'entreprise : connexion explicite.
            'plan_id' => ['nullable', 'integer', Rule::exists(config('tenancy.database.central_connection').'.plans', 'id')->where('is_active', true)->where('is_trial', false)],
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'method' => ['required', Rule::in(array_keys(SubscriptionPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:190', Rule::requiredIf(fn () => in_array($request->input('method'), ['transfer', 'mobile_money'], true))],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
        ], ['reference.required' => 'Indiquez la référence de votre virement ou transaction : elle nous permet de retrouver le paiement.']);

        if (SubscriptionPayment::where('tenant_id', $tenant->id)->where('status', 'pending')->count() >= self::MAX_PENDING) {
            abort(422, 'Vous avez déjà plusieurs paiements en attente de validation. Patientez avant d\'en déclarer un autre.');
        }

        $payment = $subscriptions->declarePayment($tenant, $data);

        return response()->json($payment->load('plan:id,name'), 201);
    }
}
