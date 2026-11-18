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
    <div class="w-full max-w-md rounded-2xl border border-line bg-surface p-6 motion-safe:animate-[c-rise_.35s_ease_both] sm:p-8">
        <div class="mb-5 inline-flex items-center gap-2.5 font-display text-lg font-semibold">
            <span class="grid size-8 place-items-center rounded-lg bg-accent text-sm font-bold text-on-accent" aria-hidden="true">G</span>GestCom Console
        </div>
        @include('console._flash')
        @yield('content')
    </div>
</main>
</body>
</html>
