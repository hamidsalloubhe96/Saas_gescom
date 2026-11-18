<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Une connexion d'assistance en LECTURE SEULE (jeton « impersonation » sans le droit « * ») ne peut jamais modifier de données :
 * toute requête autre que GET, HEAD ou OPTIONS est refusée par le serveur (pas seulement masquée dans l'interface).
 */
class ImpersonationReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && ($token->name ?? null) === 'impersonation'
            && ! in_array('*', $token->abilities ?? [], true)
            && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return response()->json([
                'message' => 'Connexion d\'assistance en lecture seule : aucune modification n\'est possible.',
                'code' => 'impersonation_read_only',
            ], 403);
        }

        return $next($request);
    }
}
