<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Connexion') · GestCom Console</title>
    @include('console._styles')
    @stack('head')
</head>
<body>
<main class="grid min-h-screen place-items-center px-4 py-8">
    {{-- Une seule carte en deux panneaux : présentation à gauche (à partir de lg), formulaire à droite. --}}
    <div class="grid w-full max-w-4xl overflow-hidden rounded-3xl border border-line bg-surface shadow-xl motion-safe:animate-[c-rise_.35s_ease_both] lg:grid-cols-[44%_1fr]">
        <aside class="relative hidden flex-col overflow-hidden bg-side p-9 text-side-ink lg:flex">
            <span class="pointer-events-none absolute -bottom-24 -right-24 size-80 rounded-full border border-white/10" aria-hidden="true"></span>
            <span class="pointer-events-none absolute -bottom-10 -right-10 size-56 rounded-full border border-white/10" aria-hidden="true"></span>
            <span class="inline-flex items-center gap-2.5 font-display text-lg font-semibold text-white">
                <span class="grid size-8 place-items-center rounded-lg bg-accent text-sm font-bold text-on-accent" aria-hidden="true">G</span>GestCom
            </span>
            <div class="mt-auto">
                <p class="text-sm font-semibold text-brand-300">Console de la plateforme</p>
                <h2 class="mt-2 text-3xl font-semibold leading-tight text-white">Administration de GestCom</h2>
                <p class="mt-3 max-w-xs text-[.95rem]">Suivi des entreprises, des abonnements et des paiements de la plateforme.</p>
            </div>
            <p class="relative mt-10 flex items-center gap-2 text-sm text-white/80">
                <span class="grid size-6 place-items-center rounded-md border border-white/25"><x-icon name="team" class="size-3.5" /></span>
                Accès réservé à l’équipe GestCom
            </p>
        </aside>

        <section class="p-6 sm:p-9">
            <div class="mb-5 inline-flex items-center gap-2.5 font-display text-lg font-semibold lg:hidden">
                <span class="grid size-8 place-items-center rounded-lg bg-accent text-sm font-bold text-on-accent" aria-hidden="true">G</span>GestCom Console
            </div>
            @include('console._flash')
            @yield('content')
        </section>
    </div>
</main>
</body>
</html>
