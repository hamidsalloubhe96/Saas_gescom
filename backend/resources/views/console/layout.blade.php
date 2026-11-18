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
@endphp
<div class="shell">
    <aside class="sidebar">
        <div class="brand"><span class="brand-mark">G</span><span>GestCom<small>Console</small></span></div>
        <nav aria-label="Navigation de la console">
            @foreach($groups as $title => $items)
                @php($visible = collect($items)->filter(fn ($i) => \Illuminate\Support\Facades\Route::has($i[1]) && ($i[2] === null || $admin->can($i[2]))))
                @if($visible->isNotEmpty())
                    <div class="nav-group">{{ $title }}</div>
                    @foreach($visible as [$label, $route, , $patterns, $count])
                        <a href="{{ route($route) }}" @class(['active' => request()->routeIs(...$patterns)])>
                            {{ $label }}@if($count)<span class="nav-badge">{{ $count }}</span>@endif
                        </a>
                    @endforeach
                @endif
            @endforeach
            <div class="nav-group">Compte</div>
            <a href="{{ route('console.password') }}" @class(['active' => request()->routeIs('console.password*', 'console.twofactor.setup*')])>Mon compte</a>
        </nav>
        <div class="side-foot">
            <strong>{{ $admin->name }}</strong>
            <span>{{ $admin->roleLabel() }}</span>
            <div class="links"><button type="button" class="btn btn-ghost btn-sm" onclick="toggleTheme()">Thème clair / sombre</button></div>
            <form method="POST" action="{{ route('console.logout') }}">@csrf
                <button class="btn btn-ghost">Se déconnecter</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="mobile-top">
            <span><strong>{{ $admin->name }}</strong> · {{ $admin->roleLabel() }}</span>
            <form method="POST" action="{{ route('console.logout') }}">@csrf<button class="btn btn-secondary btn-sm">Quitter</button></form>
        </div>

        @include('console._flash')
        @yield('content')
    </main>
</div>
</body>
</html>
