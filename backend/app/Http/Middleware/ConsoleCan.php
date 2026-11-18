<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage : ->middleware('console.can:tenants.suspend'). Le rôle de l'admin est vérifié côté serveur à chaque requête. */
class ConsoleCan
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $admin = auth('admin')->user();

        if (! $admin || ! $admin->can($ability)) {
            abort(403, 'Votre rôle ne vous permet pas cette action.');
        }

        return $next($request);
    }
}
