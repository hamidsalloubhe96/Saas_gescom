<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque l'API d'une entreprise suspendue ou bannie par la console.
 * Exception : une entreprise suspendue À CAUSE DE SON ABONNEMENT peut encore se connecter et régler,
 * sinon elle n'aurait aucun moyen de se réactiver. À placer APRÈS l'identification du tenant.
 */
class EnsureTenantIsActive
{
    /** Routes restant ouvertes à une entreprise dont l'abonnement est terminé. */
    private const BILLING_PATHS = ['api/login', 'api/me', 'api/logout', 'api/password', 'api/password/*', 'api/subscription', 'api/subscription/*', 'api/support', 'api/support/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant instanceof Tenant && ! $tenant->isUsable()) {
            if ($tenant->status === Tenant::STATUS_SUSPENDED && $tenant->suspension_source === 'subscription') {
                if ($request->is(...self::BILLING_PATHS)) {
                    return $next($request);
                }

                return response()->json([
                    'message' => 'Votre abonnement est terminé. Réglez-le depuis la page « Abonnement » pour retrouver l\'accès.',
                    'code' => 'subscription_expired',
                    'reason' => $tenant->suspended_reason,
                ], 403);
            }

            if ($tenant->status === Tenant::STATUS_PENDING_DELETION) {
                return response()->json([
                    'message' => 'Cet espace est fermé et sera supprimé le '.$tenant->deletion_scheduled_at?->format('d/m/Y').'. Contactez le support pour l\'annuler.',
                    'code' => 'tenant_pending_deletion',
                ], 403);
            }

            $banned = $tenant->status === Tenant::STATUS_BANNED;

            return response()->json([
                'message' => $banned
                    ? 'Cet espace a été fermé par la plateforme. Contactez le support.'
                    : 'Cet espace est suspendu. Contactez le support pour le réactiver.',
                'code' => $banned ? 'tenant_banned' : 'tenant_suspended',
                'reason' => $tenant->suspended_reason,
            ], 403);
        }

        return $next($request);
    }
}
