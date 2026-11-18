<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Règles de sécurité appliquées à chaque page de la console, une fois connecté :
 *  1. un compte désactivé est déconnecté immédiatement ;
 *  2. un mot de passe temporaire doit être changé avant toute chose ;
 *  3. un super administrateur doit avoir activé la double authentification.
 */
class ConsoleSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();
        if (! $admin) {
            return $next($request);
        }

        if (! $admin->is_active) {
            auth('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('console.login')->withErrors(['email' => 'Ce compte est désactivé.']);
        }

        $always = ['console.logout'];

        if ($admin->must_change_password && ! $request->routeIs('console.password*', ...$always)) {
            return redirect()->route('console.password')->with('warning', 'Choisissez un nouveau mot de passe pour continuer.');
        }

        if ($admin->isSuper() && ! $admin->two_factor_confirmed_at
            && ! $request->routeIs('console.password*', 'console.twofactor.setup*', ...$always)) {
            return redirect()->route('console.twofactor.setup')
                ->with('warning', 'La double authentification est obligatoire pour un super administrateur.');
        }

        return $next($request);
    }
}
