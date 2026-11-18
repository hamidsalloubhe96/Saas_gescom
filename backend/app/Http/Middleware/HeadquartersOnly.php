<?php

namespace App\Http\Middleware;

use App\Services\CurrentBranch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Réservé aux utilisateurs du siège : un utilisateur rattaché à un point de vente reçoit 403. */
class HeadquartersOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(CurrentBranch::class)->isRestricted()) {
            return response()->json(['message' => 'Cette action est réservée au siège.', 'code' => 'headquarters_only'], 403);
        }

        return $next($request);
    }
}
