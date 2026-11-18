@extends('console.layout')
@section('title', 'Entreprises')
@php
    $tone = ['active' => 'pill-ok', 'suspended' => 'pill-warn', 'banned' => 'pill-bad'];
@endphp
@section('content')
    <header class="page-head">
        <div>
            <h1>Entreprises</h1>
            <p class="muted">{{ $tenants->total() }} résultat(s)</p>
        </div>
        @if($admin->can('tenants.create'))
            <a class="btn" href="{{ route('console.tenants.create') }}">+ Créer une entreprise</a>
        @endif
    </header>

    <form method="GET" class="toolbar" role="search">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher (nom, identifiant, e-mail)…" aria-label="Rechercher">
        <select name="status" aria-label="Filtrer par statut" onchange="this.form.submit()">
            <option value="">Tous les statuts</option>
            @foreach(\App\Models\Tenant::STATUSES as $k => $label)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }} ({{ $counts[$k] ?? 0 }})</option>
            @endforeach
        </select>
        <button class="btn btn-secondary">Filtrer</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Entreprise</th><th>Identifiant</th><th>Fondateur</th><th>Statut</th><th>Créée le</th><th>Dernière connexion</th></tr></thead>
            <tbody>
            @forelse($tenants as $t)
                <tr>
                    <td><a href="{{ route('console.tenants.show', $t) }}"><strong>{{ $t->name }}</strong></a>
                        @if($t->city || $t->country)<div class="muted small">{{ collect([$t->city, $t->country])->filter()->implode(', ') }}</div>@endif</td>
                    <td><code>{{ $t->id }}</code></td>
                    <td>{{ $t->owner_email ?? '—' }}</td>
                    <td><span class="pill {{ $tone[$t->status] ?? '' }}">{{ $t->statusLabel() }}</span></td>
                    <td>{{ $t->created_at?->format('d/m/Y') }}</td>
                    <td>{{ $t->last_activity_at?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Aucune entreprise ne correspond.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('console._pager', ['paginator' => $tenants])
@endsection
