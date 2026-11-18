<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage : ->middleware('permission:sales.view') ou 'permission:a,b' (au moins une des permissions). */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Ce compte est désactivé.');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'Vous n\'avez pas les droits nécessaires pour cette action.');
    }
}
