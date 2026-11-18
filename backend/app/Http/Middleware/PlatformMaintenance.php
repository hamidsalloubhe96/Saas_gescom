<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode maintenance de la plateforme (réglé dans la console) : l'API des entreprises répond 503.
 * La console, elle, reste toujours accessible.
 */
class PlatformMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PlatformSetting::bool('maintenance')) {
            return response()->json([
                'message' => PlatformSetting::get('maintenance_message') ?: 'La plateforme est en maintenance. Réessayez dans quelques minutes.',
                'code' => 'maintenance',
            ], 503)->header('Retry-After', '300');
        }

        return $next($request);
    }
}
