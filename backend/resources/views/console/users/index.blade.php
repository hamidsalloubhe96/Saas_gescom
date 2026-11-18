@extends('console.layout')
@section('title', 'Utilisateurs')
@section('content')
    <header class="page-head">
        <div>
            <h1>Utilisateurs</h1>
            <p class="muted">{{ $users->total() }} résultat(s), toutes entreprises confondues.</p>
        </div>
    </header>

    <form method="GET" class="toolbar" role="search">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher par e-mail ou nom…" aria-label="Rechercher" autofocus>
        <input type="text" name="tenant" value="{{ request('tenant') }}" placeholder="Identifiant d'entreprise" aria-label="Entreprise">
        <select name="status" aria-label="Statut" onchange="this.form.submit()">
            <option value="">Tous</option>
            <option value="active" @selected(request('status') === 'active')>Actifs</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Désactivés</option>
        </select>
        <button class="btn btn-secondary">Rechercher</button>
    </form>

    @error('impersonate')<div class="alert alert-bad" role="alert">{{ $message }}</div>@enderror

    <div class="table-wrap">
        <table>
            <thead><tr><th>Utilisateur</th><th>Entreprise</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($users as $u)
                <tr>
                    <td><strong>{{ $u->name }}</strong><div class="muted small">{{ $u->email }}</div></td>
                    <td>@if($u->tenant)<a href="{{ route('console.tenants.show', $u->tenant) }}">{{ $u->tenant->name }}</a>
                        <div class="muted small"><code>{{ $u->tenant_id }}</code></div>@else <span class="muted">supprimée</span> @endif</td>
                    <td>{{ config("permissions.roles.{$u->role}.label", $u->role) }}</td>
                    <td><span class="pill {{ $u->is_active ? 'pill-ok' : '' }}">{{ $u->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                    <td>{{ $u->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                    <td>
                        <div class="actions-row">
                            @if($admin->can('users.manage'))
                                <form method="POST" action="{{ route('console.users.toggle', $u) }}">@csrf
                                    <button class="btn btn-secondary btn-sm">{{ $u->is_active ? 'Désactiver' : 'Réactiver' }}</button></form>
                                <form method="POST" action="{{ route('console.users.logout', $u) }}">@csrf
                                    <button class="btn btn-secondary btn-sm">Déconnecter</button></form>
                                <form method="POST" action="{{ route('console.users.reset', $u) }}"
                                      onsubmit="return confirm('Générer un mot de passe provisoire pour {{ $u->email }} ?')">@csrf
                                    <button class="btn btn-secondary btn-sm">Réinit. mot de passe</button></form>
                            @endif
                            @if($admin->can('users.impersonate') && $u->is_active)
                                <form method="POST" action="{{ route('console.users.impersonate', $u) }}" target="_blank">@csrf
                                    <input type="hidden" name="mode" value="read">
                                    <button class="btn btn-sm">Assistance (lecture seule)</button></form>
                            @endif
                        </div>
                        @if($admin->can('users.impersonate.write') && $u->is_active)
                            <details class="danger" style="margin-top:.5rem">
                                <summary class="small">Avec droit de modification…</summary>
                                <form method="POST" action="{{ route('console.users.impersonate', $u) }}" target="_blank" style="margin-top:.5rem">@csrf
                                    <input type="hidden" name="mode" value="write">
                                    <div class="field"><label for="r{{ $u->id }}">Motif *</label><input id="r{{ $u->id }}" name="reason" minlength="5" required></div>
                                    <button class="btn btn-danger btn-sm">Se connecter (modification)</button>
                                </form>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Aucun utilisateur ne correspond.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('console._pager', ['paginator' => $users])
@endsection
