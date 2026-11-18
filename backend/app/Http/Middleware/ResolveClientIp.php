<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adresse IP réelle du visiteur derrière les serveurs intermédiaires (Render, Cloudflare).
 *
 * Chaque intermédiaire de confiance AJOUTE l'adresse qui s'est connectée à lui à la fin de X-Forwarded-For. Un visiteur ne
 * peut donc falsifier que les entrées de gauche : on lit l'entrée située « TRUSTED_PROXY_HOPS » rangs avant la fin.
 *   - 0 (défaut) : aucun intermédiaire (développement local), REMOTE_ADDR est conservé ;
 *   - 1 : Render seul devant l'application ;
 *   - 2 : Cloudflare puis Render.
 * Sans cela, toutes les requêtes semblent venir de l'intermédiaire et les limites de tentatives s'appliquent à tout le monde ensemble.
 */
class ResolveClientIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $hops = (int) config('app.trusted_proxy_hops', 0);
        $header = (string) $request->server->get('HTTP_X_FORWARDED_FOR', '');

        if ($hops > 0 && $header !== '') {
            $entries = array_values(array_filter(array_map('trim', explode(',', $header)), fn ($e) => $e !== ''));
            $ip = $entries[max(0, count($entries) - $hops)] ?? null;
            if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP)) {
                $request->server->set('REMOTE_ADDR', $ip);
            }
        }

        return $next($request);
    }
}
