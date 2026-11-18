@extends('console.layout')
@section('title', 'Échéances')
@php
    $tone = ['trial' => 'pill-info', 'active' => 'pill-ok', 'past_due' => 'pill-warn', 'expired' => 'pill-bad', 'cancelled' => ''];
    $tabs = ['all' => 'Toutes', 'overdue' => 'En retard ('.$counts['overdue'].')', 'soon' => 'Dans 7 jours', 'trial' => 'Essais ('.$counts['trial'].')', 'expired' => 'Expirés / résiliés ('.$counts['expired'].')'];
@endphp
@section('content')
    <header class="page-head">
        <div><h1>Échéances</h1><p class="muted">{{ $subs->total() }} abonnement(s). Les plus urgents en premier.</p></div>
    </header>

    <div class="toolbar">
        @foreach($tabs as $k => $label)
            <a class="btn {{ $filter === $k ? '' : 'btn-secondary' }} btn-sm" href="{{ route('console.dues.index', ['filter' => $k]) }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Entreprise</th><th>Forfait</th><th>Statut</th><th>Échéance</th><th>Jours restants</th></tr></thead>
            <tbody>
            @forelse($subs as $s)
                @php($left = $s->daysLeft())
                <tr>
                    <td><a href="{{ route('console.tenants.show', $s->tenant) }}"><strong>{{ $s->tenant->name }}</strong></a><div class="muted small"><code>{{ $s->tenant_id }}</code></div></td>
                    <td>{{ $s->plan->name }}</td>
                    <td><span class="pill {{ $tone[$s->status] ?? '' }}">{{ $s->statusLabel() }}</span></td>
                    <td>{{ $s->endsAt()?->format('d/m/Y') ?? '—' }}@if($s->status === 'past_due' && $s->grace_ends_at)<div class="muted small">Coupure le {{ $s->grace_ends_at->format('d/m/Y') }}</div>@endif</td>
                    <td class="{{ $left !== null && $left < 0 ? 'qty-neg' : '' }}">@if($left === null) — @elseif($left < 0) En retard de {{ abs($left) }} j @else {{ $left }} j @endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Rien dans cette catégorie.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('console._pager', ['paginator' => $subs])
@endsection
