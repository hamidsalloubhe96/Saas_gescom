<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \App\Http\Middleware\RequirePermission::class,
            'tenant.active' => \App\Http\Middleware\EnsureTenantIsActive::class,
            'maintenance' => \App\Http\Middleware\PlatformMaintenance::class,
            'hq' => \App\Http\Middleware\HeadquartersOnly::class,
            'impersonation.readonly' => \App\Http\Middleware\ImpersonationReadOnly::class,
            'console.can' => \App\Http\Middleware\ConsoleCan::class,
            'console.security' => \App\Http\Middleware\ConsoleSecurity::class,
        ]);

        // Console : un invité va sur /console/login ; un admin déjà connecté va sur son tableau de bord.
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $r) => $r->is('console', 'console/*') ? route('console.login') : null);
        $middleware->redirectUsersTo(fn (\Illuminate\Http\Request $r) => $r->is('console', 'console/*') ? route('console.dashboard') : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Erreur grave non gérée : alerte par e-mail (si CONSOLE_ALERT_EMAIL est défini), sans jamais casser la requête.
        $exceptions->dontReport(\Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException::class);
        $exceptions->report(function (\Throwable $e) {
            app(\App\Services\ErrorAlert::class)->notify($e);
        });

        $exceptions->render(function (\Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException $e, Request $request) {
            return response()->json(['message' => 'Entreprise introuvable ou en-tête X-Tenant manquant.'], 404);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
