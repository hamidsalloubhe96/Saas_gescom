@extends('console.layout')
@section('title', 'Journal d\'activité')
@section('content')
    <header class="page-head">
        <div>
            <h1>Journal d'activité</h1>
            <p class="muted">{{ $logs->total() }} entrée(s) : tout ce que fait l'équipe dans la console.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('console.audit.export', request()->query()) }}">Exporter en CSV</a>
    </header>

    <form method="GET" class="toolbar">
        <select name="action" aria-label="Type d'action">
            <option value="">Toutes les actions</option>
            @foreach($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>@endforeach
        </select>
        <input type="text" name="tenant" value="{{ request('tenant') }}" placeholder="Identifiant d'entreprise" aria-label="Entreprise">
        <input type="date" name="from" value="{{ request('from') }}" aria-label="Du">
        <input type="date" name="to" value="{{ request('to') }}" aria-label="Au">
        <button class="btn btn-secondary">Filtrer</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Administrateur</th><th>Action</th><th>Entreprise</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($logs as $l)
                <tr>
                    <td style="white-space:nowrap">{{ $l->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $l->admin_name ?? '—' }}</td>
                    <td><code>{{ $l->action }}</code></td>
                    <td>@if($l->tenant_id)<a href="{{ route('console.tenants.show', $l->tenant_id) }}">{{ $l->tenant_id }}</a>@else — @endif</td>
                    <td>{{ $l->description }}</td>
                    <td class="muted small">{{ $l->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Aucune entrée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('console._pager', ['paginator' => $logs])
@endsection
