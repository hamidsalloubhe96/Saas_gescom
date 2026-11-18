@extends('console.layout')
@section('title', 'Paiements')
@php($fmt = fn ($p) => number_format((float) $p->amount, 0, ',', ' ').' '.$p->currency)
@section('content')
    <header class="page-head">
        <div><h1>Paiements</h1><p class="muted">Paiements déclarés par les entreprises à vérifier, puis historique.</p></div>
    </header>

    <section class="panel">
        <h2>À valider ({{ $pending->count() }})</h2>
        @if($pending->isEmpty())
            <p class="muted" style="margin:0">Aucun paiement en attente.</p>
        @else
            <div class="table-wrap" style="border:0">
                <table>
                    <thead><tr><th>Entreprise</th><th>Montant</th><th>Détail</th><th>Déclaré le</th><th>Actions</th></tr></thead>
                    <tbody>
                    @foreach($pending as $p)
                        <tr>
                            <td><a href="{{ route('console.tenants.show', $p->tenant) }}"><strong>{{ $p->tenant->name }}</strong></a><div class="muted small"><code>{{ $p->tenant_id }}</code></div></td>
                            <td><strong>{{ $fmt($p) }}</strong><div class="muted small">{{ $p->months }} période(s)</div></td>
                            <td>{{ $p->methodLabel() }} @if($p->reference)· réf. <code>{{ $p->reference }}</code>@endif<div class="muted small">Forfait : {{ $p->plan?->name ?? '—' }} · payé le {{ $p->paid_at?->format('d/m/Y') }}</div></td>
                            <td>{{ $p->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="actions-row">
                                    <form method="POST" action="{{ route('console.payments.validate', $p) }}" onsubmit="return confirm('Valider ce paiement de {{ $fmt($p) }} ? L\'abonnement sera prolongé.')">@csrf
                                        <button class="btn btn-sm">Valider</button></form>
                                    <details class="danger"><summary class="small">Refuser…</summary>
                                        <form method="POST" action="{{ route('console.payments.reject', $p) }}" style="margin-top:.5rem">@csrf
                                            <div class="field"><label for="n{{ $p->id }}">Motif *</label><input id="n{{ $p->id }}" name="note" required minlength="3"></div>
                                            <button class="btn btn-danger btn-sm">Refuser</button></form>
                                    </details>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <h2 style="margin:1.4rem 0 .6rem">Historique</h2>
    <form method="GET" class="toolbar">
        <select name="status" aria-label="Statut" onchange="this.form.submit()">
            <option value="">Tous</option>
            <option value="validated" @selected(request('status') === 'validated')>Validés</option>
            <option value="rejected" @selected(request('status') === 'rejected')>Refusés</option>
        </select>
        <input name="tenant" value="{{ request('tenant') }}" placeholder="Identifiant d'entreprise" aria-label="Entreprise">
        <button class="btn btn-secondary">Filtrer</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Entreprise</th><th>Montant</th><th>Mode</th><th>Statut</th><th>Traité par</th></tr></thead>
            <tbody>
            @forelse($history as $p)
                <tr>
                    <td>{{ $p->created_at->format('d/m/Y') }}</td>
                    <td>{{ $p->tenant?->name ?? $p->tenant_id }}</td>
                    <td>{{ $fmt($p) }} <span class="muted small">/ {{ $p->months }} p.</span></td>
                    <td>{{ $p->methodLabel() }}@if($p->reference) <code class="small">{{ $p->reference }}</code>@endif @if($p->source === 'console')<span class="pill pill-info">hors ligne</span>@endif</td>
                    <td><span class="pill {{ $p->status === 'validated' ? 'pill-ok' : 'pill-bad' }}">{{ $p->status === 'validated' ? 'Validé' : 'Refusé' }}</span>@if($p->note)<div class="muted small">{{ $p->note }}</div>@endif</td>
                    <td>{{ $p->validator?->name ?? '—' }}@if($p->status === 'validated') <div><a class="small" href="{{ route('console.payments.receipt', $p) }}" target="_blank" rel="noopener">Reçu</a></div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Aucun paiement traité.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('console._pager', ['paginator' => $history])
@endsection
