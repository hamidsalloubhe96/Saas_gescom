@extends('console.layout')
@section('title', 'Équipe')
@php($me = auth('admin')->user())
@section('content')
    <header class="page-head">
        <div>
            <h1>Équipe de la console</h1>
            <p class="muted">{{ $admins->count() }} compte(s). Chaque rôle donne accès à une partie de la console.</p>
        </div>
    </header>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>2FA</th><th>Dernière connexion</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            @foreach($admins as $a)
                <tr>
                    <td><strong>{{ $a->name }}</strong>@if($a->id === $me->id)<span class="muted small"> (vous)</span>@endif</td>
                    <td>{{ $a->email }}</td>
                    <td>
                        @if($a->id === $me->id)
                            {{ $a->roleLabel() }}
                        @else
                            <form method="POST" action="{{ route('console.team.update', $a) }}" style="display:flex;gap:.4rem;align-items:center">
                                @csrf @method('PUT')
                                <input type="hidden" name="is_active" value="{{ $a->is_active ? 1 : 0 }}">
                                <select name="role" aria-label="Rôle de {{ $a->name }}" onchange="this.form.submit()" style="padding:.35rem;border:1px solid var(--border);border-radius:6px;background:var(--surface)">
                                    @foreach(\App\Models\PlatformAdmin::ROLES as $k => $label)<option value="{{ $k }}" @selected($a->role === $k)>{{ $label }}</option>@endforeach
                                </select>
                            </form>
                        @endif
                    </td>
                    <td>{!! $a->two_factor_confirmed_at ? '<span class="pill pill-ok">Oui</span>' : '<span class="pill pill-warn">Non</span>' !!}</td>
                    <td>{{ $a->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                    <td><span class="pill {{ $a->is_active ? 'pill-ok' : '' }}">{{ $a->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                    <td style="white-space:nowrap">
                        @if($a->id !== $me->id)
                            <form method="POST" action="{{ route('console.team.update', $a) }}" style="display:inline">
                                @csrf @method('PUT')
                                <input type="hidden" name="role" value="{{ $a->role }}">
                                <input type="hidden" name="is_active" value="{{ $a->is_active ? 0 : 1 }}">
                                <button class="btn btn-secondary btn-sm">{{ $a->is_active ? 'Désactiver' : 'Activer' }}</button>
                            </form>
                            <form method="POST" action="{{ route('console.team.reset', $a) }}" style="display:inline"
                                  onsubmit="return confirm('Générer un nouveau mot de passe provisoire pour {{ $a->email }} ?')">@csrf
                                <button class="btn btn-secondary btn-sm">Réinitialiser le mot de passe</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <section class="panel" style="margin-top:1rem;max-width:720px">
        <h2>Ajouter un membre</h2>
        <form method="POST" action="{{ route('console.team.store') }}" novalidate>
            @csrf
            <div class="row">
                <div class="field"><label for="n">Nom *</label><input id="n" name="name" value="{{ old('name') }}" required></div>
                <div class="field"><label for="e">E-mail *</label><input id="e" name="email" type="email" value="{{ old('email') }}" required></div>
                <div class="field"><label for="r">Rôle *</label>
                    <select id="r" name="role">@foreach(\App\Models\PlatformAdmin::ROLES as $k => $label)<option value="{{ $k }}" @selected(old('role', 'admin') === $k)>{{ $label }}</option>@endforeach</select>
                </div>
            </div>
            <button class="btn">Créer le compte</button>
        </form>
    </section>

    <section class="panel">
        <h2>Droits de chaque rôle</h2>
        <div class="table-wrap" style="border:0">
            <table>
                <thead><tr><th>Droit</th>@foreach(\App\Models\PlatformAdmin::ROLES as $label)<th style="text-align:center">{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($abilities as $key => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        @foreach(array_keys(\App\Models\PlatformAdmin::ROLES) as $role)
                            <td style="text-align:center">{{ in_array('*', $roles[$role] ?? [], true) || in_array($key, $roles[$role] ?? [], true) ? '✓' : '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
