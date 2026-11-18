@extends('console.layout')
@section('title', 'Limites')
@php
    $tierLabel = ['normal' => 'Normal', 'warning' => 'Alerte (80 %)', 'critical' => 'Critique (95 %)', 'reached' => 'Atteint'];
    $tierPill = ['normal' => 'pill-ok', 'warning' => 'pill-warn', 'critical' => 'pill-bad', 'reached' => 'pill-bad'];
@endphp
@section('content')
    <header class="page-head">
        <div><h1>Limites d'usage</h1>
            <p class="muted">À 100 %, les <strong>nouvelles créations</strong> sont refusées ; les données existantes ne sont jamais touchées.</p></div>
    </header>

    <div class="toolbar">
        <a class="btn {{ request('tier') ? 'btn-secondary' : '' }} btn-sm" href="{{ route('console.quotas.index') }}">Toutes</a>
        @foreach(['reached', 'critical', 'warning'] as $t)
            <a class="btn {{ request('tier') === $t ? '' : 'btn-secondary' }} btn-sm" href="{{ route('console.quotas.index', ['tier' => $t]) }}">{{ $tierLabel[$t] }} ({{ $counts[$t] ?? 0 }})</a>
        @endforeach
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Entreprise</th>@foreach($resources as $label)<th>{{ ucfirst($label) }}</th>@endforeach<th>Palier</th>@if($admin->can('quotas.manage'))<th>Actions</th>@endif</tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td><a href="{{ route('console.tenants.show', $r['tenant']) }}"><strong>{{ $r['tenant']->name }}</strong></a>
                        <div class="muted small">{{ $r['sub']?->plan->name ?? '—' }}@if($r['sub']?->custom_limits) · limites personnalisées @endif
                            @if($r['sub']?->limitsUnlocked()) · <strong>débloquées jusqu'au {{ $r['sub']->limits_unlocked_until->format('d/m') }}</strong>@endif</div></td>
                    @foreach($resources as $key => $label)
                        @php($lim = $r['lim'][$key]) @php($used = $r['usage'][$key]) @php($t = $r['tiers'][$key])
                        <td>
                            <div>{{ number_format($used, 0, ',', ' ') }} / {{ \App\Models\Plan::limitLabel($lim) }}</div>
                            @if($lim !== null)<div class="bar {{ $t }}" aria-hidden="true"><span style="width:{{ min(100, $lim > 0 ? $used / $lim * 100 : 100) }}%"></span></div>@endif
                        </td>
                    @endforeach
                    <td><span class="pill {{ $tierPill[$r['worst']] }}">{{ $tierLabel[$r['worst']] }}</span></td>
                    @if($admin->can('quotas.manage'))
                        <td>
                            <details><summary class="small">Gérer</summary>
                                <form method="POST" action="{{ route('console.quotas.update', $r['tenant']) }}" style="margin-top:.6rem;min-width:240px">@csrf @method('PUT')
                                    @foreach($resources as $key => $label)
                                        <div class="field" style="margin-bottom:.4rem"><label class="small" for="{{ $key.$r['tenant']->id }}">Limite : {{ $label }}</label>
                                            <input id="{{ $key.$r['tenant']->id }}" name="{{ $key }}" type="number" min="1" value="{{ $r['sub']?->custom_limits[$key] ?? '' }}" placeholder="du forfait ({{ \App\Models\Plan::limitLabel($r['lim'][$key]) }})"></div>
                                    @endforeach
                                    <button class="btn btn-secondary btn-sm">Enregistrer</button>
                                </form>
                                @if($r['sub']?->limitsUnlocked())
                                    <form method="POST" action="{{ route('console.quotas.relock', $r['tenant']) }}" style="margin-top:.4rem">@csrf<button class="btn btn-secondary btn-sm">Rebloquer</button></form>
                                @else
                                    <form method="POST" action="{{ route('console.quotas.unlock', $r['tenant']) }}" style="margin-top:.4rem;display:flex;gap:.4rem">@csrf
                                        <input name="days" type="number" min="1" max="90" value="7" style="width:70px;padding:.35rem" aria-label="Jours"><button class="btn btn-secondary btn-sm">Débloquer (jours)</button></form>
                                @endif
                            </details>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Aucune entreprise dans cette catégorie.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
