<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notification de paiement d'un prestataire (mobile money, carte…). Le corps est signé en HMAC-SHA256 avec
 * PAYMENT_WEBHOOK_SECRET (en-tête X-Signature) ; sans secret configuré, la route est fermée.
 * Charge utile : { "reference": "...", "amount": 25000, "status": "paid" }. Le paiement doit avoir été déclaré (même référence).
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, SubscriptionService $subscriptions): JsonResponse
    {
        $secret = config('services.payment_webhook.secret');
        abort_if(! $secret, 404);

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Signature')), 401, 'Signature invalide.');

        $data = $request->validate(['reference' => ['required', 'string', 'max:190'], 'amount' => ['required', 'numeric'], 'status' => ['required', 'in:paid,failed']]);

        $payment = SubscriptionPayment::where('reference', $data['reference'])->where('status', 'pending')->first();
        if (! $payment) {
            return response()->json(['message' => 'Aucun paiement en attente avec cette référence.'], 202);
        }
        if ($data['status'] === 'failed') {
            $subscriptions->rejectPayment($payment, null, 'Refusé par le prestataire de paiement');

            return response()->json(['message' => 'Paiement refusé.']);
        }
        // Le montant reçu doit couvrir le montant déclaré : sinon la validation reste manuelle.
        if ((float) $data['amount'] < (float) $payment->amount) {
            return response()->json(['message' => 'Montant insuffisant : validation manuelle requise.'], 202);
        }

        $payment->update(['source' => 'webhook']);
        $subscriptions->validatePayment($payment, null, 'webhook');
        AuditLog::record('payment.webhook', "Paiement {$payment->reference} validé automatiquement", $payment->tenant_id, ['payment' => $payment->id]);

        return response()->json(['message' => 'Paiement validé.']);
    }
}
