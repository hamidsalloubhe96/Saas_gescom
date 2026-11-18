<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Paiements d'abonnement : ceux déclarés par les entreprises à valider ou refuser, et l'historique. */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'in:pending,validated,rejected'], 'tenant' => ['nullable', 'string', 'max:60']]);

        $history = SubscriptionPayment::with(['tenant', 'plan', 'validator'])->where('status', '!=', 'pending')->latest('id');
        if ($status = $request->query('status')) {
            $history->where('status', $status);
        }
        if ($tenant = $request->query('tenant')) {
            $history->where('tenant_id', $tenant);
        }

        return view('console.payments.index', [
            'pending' => SubscriptionPayment::with(['tenant', 'plan'])->where('status', 'pending')->oldest('id')->get(),
            'history' => $history->paginate(20)->withQueryString(),
        ]);
    }

    public function receipt(SubscriptionPayment $payment)
    {
        return view('console.payments.receipt', ['r' => $payment->receipt()]);
    }

    public function validatePayment(SubscriptionPayment $payment, SubscriptionService $subscriptions): RedirectResponse
    {
        $payment = $subscriptions->validatePayment($payment, auth('admin')->user());

        AuditLog::record('payment.validate', "Paiement de {$payment->amount} {$payment->currency} validé pour {$payment->tenant_id} ({$payment->months} période(s))",
            $payment->tenant_id, ['payment' => $payment->id, 'reference' => $payment->reference]);

        return back()->with('success', 'Paiement validé : l\'abonnement est prolongé et l\'accès rétabli si besoin.');
    }

    public function reject(Request $request, SubscriptionPayment $payment, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:255']], ['note.required' => 'Indiquez le motif du refus.']);
        $payment = $subscriptions->rejectPayment($payment, auth('admin')->user(), $data['note']);

        AuditLog::record('payment.reject', "Paiement de {$payment->amount} {$payment->currency} refusé pour {$payment->tenant_id} : {$data['note']}",
            $payment->tenant_id, ['payment' => $payment->id]);

        return back()->with('success', 'Paiement refusé.');
    }
}
