<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Console') · GestCom Console</title>
    @include('console._styles')
</head>
<body>
@php
    $admin = auth('admin')->user();
    // [libellé, route, droit requis, motifs de route « actif », compteur]. Une entrée n'apparaît que si sa route existe
    // ET si le rôle a le droit : les menus des phases suivantes s'ajoutent sans retoucher ce fichier.
    $groups = [
        'Pilotage' => [
            ['Tableau de bord', 'console.dashboard', null, ['console.dashboard'], null],
            ['Entreprises', 'console.tenants.index', 'tenants.view', ['console.tenants.*'], null],
            ['Utilisateurs', 'console.users.index', 'users.view', ['console.users.*'], null],
            ['Limites', 'console.quotas.index', 'quotas.view', ['console.quotas.*'], null],
            ['Échéances', 'console.dues.index', 'tenants.view', ['console.dues.*'], null],
            ['Paiements', 'console.payments.index', 'payments.validate', ['console.payments.*'], $pendingPayments ?? 0],
            ['Forfaits', 'console.plans.index', 'plans.view', ['console.plans.*'], null],
            ['Demandes', 'console.requests.index', 'requests.manage', ['console.requests.*'], null],
            ['Avis', 'console.reviews.index', 'reviews.manage', ['console.reviews.*'], null],
            ['Annonces', 'console.announcements.index', 'announcements.manage', ['console.announcements.*'], null],
            ['Support', 'console.tickets.index', 'tickets.manage', ['console.tickets.*'], null],
        ],
        'Exploitation' => [
            ['Santé', 'console.health.index', 'health.view', ['console.health.*'], null],
            ['Journal d\'activité', 'console.audit.index', 'audit.view', ['console.audit.*'], null],
            ['Équipe', 'console.team.index', 'team.manage', ['console.team.*'], null],
            ['Textes légaux', 'console.legal.index', 'legal.manage', ['console.legal.*'], null],
            ['Paramètres', 'console.settings.index', 'settings.manage', ['console.settings.*'], null],
        ],
    ];
    // Icône de chaque entrée du menu (noms dans resources/icons.json).
    $icons = ['console.dashboard' => 'dashboard', 'console.tenants.index' => 'tenants', 'console.users.index' => 'users', 'console.quotas.index' => 'quotas',
        'console.dues.index' => 'dues', 'console.payments.index' => 'payments', 'console.plans.index' => 'plans', 'console.requests.index' => 'requests',
        'console.reviews.index' => 'reviews', 'console.announcements.index' => 'announcements', 'console.tickets.index' => 'support', 'console.health.index' => 'health',
        'console.audit.index' => 'audit', 'console.team.index' => 'team', 'console.legal.index' => 'legal', 'console.settings.index' => 'settings'];
    $navLink = fn (bool $active) => 'flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-[.93rem] no-underline transition-colors duration-150 '
        .($active ? 'bg-accent font-semibold text-on-accent' : 'text-side-ink hover:bg-side-hover hover:text-white');
@endphp
<div class="min-h-screen lg:grid lg:grid-cols-[256px_minmax(0,1fr)]">
    <header class="sticky top-0 z-30 flex items-center gap-3 bg-side px-4 py-2.5 text-white lg:hidden">
        <button type="button" id="console-menu-btn" onclick="toggleDrawer(true)" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="console-sidebar"
                class="grid size-10 place-items-center rounded-lg text-side-ink hover:bg-side-hover hover:text-white"><x-icon name="menu" class="size-5" /></button>
        <span class="font-display font-semibold">GestCom <span class="text-xs font-normal text-side-ink">Console</span></span>
    </header>
    <div id="console-overlay" class="fixed inset-0 z-40 hidden bg-black/55 lg:hidden" onclick="toggleDrawer(false)" aria-hidden="true"></div>

    <aside id="console-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col gap-3 overflow-hidden bg-side p-4 text-side-ink transition-transform duration-200 ease-out lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:translate-x-0">
        <div class="flex items-center justify-between px-1">
            <span class="inline-flex items-center gap-2.5 font-display text-lg font-semibold text-white">
                <span class="grid size-8 place-items-center rounded-lg bg-accent text-sm font-bold text-on-accent" aria-hidden="true">G</span>
                <span class="leading-tight">GestCom<small class="block text-[.65rem] font-medium tracking-wider text-side-ink">CONSOLE</small></span>
            </span>
            <button type="button" onclick="toggleDrawer(false)" aria-label="Fermer le menu" class="grid size-10 place-items-center rounded-lg hover:bg-side-hover hover:text-white lg:hidden"><x-icon name="close" class="size-5" /></button>
        </div>
        <nav aria-label="Navigation de la console" class="-mr-2 flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto pr-2 [scrollbar-color:rgba(255,255,255,.25)_transparent] [scrollbar-width:thin]">
            @foreach($groups as $title => $items)
                @php($visible = collect($items)->filter(fn ($i) => \Illuminate\Support\Facades\Route::has($i[1]) && ($i[2] === null || $admin->can($i[2]))))
                @if($visible->isNotEmpty())
                    <div class="px-3 pb-1 pt-3 text-[.68rem] font-semibold tracking-wider text-side-ink/70">{{ mb_strtoupper($title) }}</div>
                    @foreach($visible as [$label, $route, , $patterns, $count])
                        <a href="{{ route($route) }}" class="{{ $navLink(request()->routeIs(...$patterns)) }}" @if(request()->routeIs(...$patterns)) aria-current="page" @endif>
                            <x-icon :name="$icons[$route] ?? 'dashboard'" />{{ $label }}@if($count)<span class="nav-badge">{{ $count }}</span>@endif
                        </a>
                    @endforeach
                @endif
            @endforeach
            <div class="px-3 pb-1 pt-3 text-[.68rem] font-semibold tracking-wider text-side-ink/70">COMPTE</div>
            <a href="{{ route('console.password') }}" class="{{ $navLink(request()->routeIs('console.password*', 'console.twofactor.setup*')) }}"><x-icon name="account" />Mon compte</a>
        </nav>
        <div class="grid shrink-0 gap-1 border-t border-white/10 px-1 pt-3 text-sm">
            <strong class="truncate text-white">{{ $admin->name }}</strong>
            <span class="text-xs">{{ $admin->roleLabel() }}</span>
            <button type="button" onclick="toggleTheme()" class="btn btn-ghost btn-sm mt-2"><x-icon name="theme" class="size-4" />Thème clair / sombre</button>
            <form method="POST" action="{{ route('console.logout') }}">@csrf
                <button class="btn btn-ghost"><x-icon name="logout" class="size-4" />Se déconnecter</button>
            </form>
        </div>
    </aside>

    <main class="mx-auto w-full min-w-0 max-w-[1250px] px-4 py-5 sm:px-6 lg:px-10 lg:py-8">
        @include('console._flash')
        @yield('content')
    </main>
</div>
</body>
</html>
