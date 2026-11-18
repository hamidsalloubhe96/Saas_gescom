@extends('console.layout')
@section('title', 'Tableau de bord')
@php
    $money = fn ($v) => number_format($v, 0, ',', ' ').' '.$currency;
    $tone = ['warn' => 'pill-warn', 'bad' => 'pill-bad', 'info' => 'pill-info'];
@endphp
@section('content')
    <header class="page-head">
        <div>
            <h1>Bonjour, {{ $admin->name }}</h1>
            <p class="muted">Vue d'ensemble de la plateforme.</p>
        </div>
        @if($admin->can('tenants.create'))<a class="btn" href="{{ route('console.tenants.create') }}">+ Créer une entreprise</a>@endif
    </header>

    <form method="GET" class="toolbar" aria-label="Filtres du tableau de bord">
        <select name="period" aria-label="Période">@foreach($periods as $k => $l)<option value="{{ $k }}" @selected($period == $k)>{{ $l }}</option>@endforeach</select>
        <select name="plan" aria-label="Forfait"><option value="">Tous les forfaits</option>@foreach($plans as $p)<option value="{{ $p->id }}" @selected($planId == $p->id)>{{ $p->name }}</option>@endforeach</select>
        <select name="country" aria-label="Pays"><option value="">Tous les pays</option>@foreach($countries as $c)<option value="{{ $c }}" @selected($country === $c)>{{ $c }}</option>@endforeach</select>
        <button class="btn btn-secondary">Filtrer</button>
        @if(request()->query())<a class="btn btn-secondary" href="{{ route('console.dashboard') }}">Réinitialiser</a>@endif
    </form>

    <section class="kpis" aria-label="Chiffres clés">
        <div class="kpi"><span class="l">Entreprises</span><span class="v">{{ $total }}</span><span class="s">{{ $counts['active'] ?? 0 }} active(s) · {{ ($counts['suspended'] ?? 0) + ($counts['banned'] ?? 0) }} suspendue(s)</span></div>
        <div class="kpi"><span class="l">Nouvelles</span><span class="v">{{ $newInPeriod }}</span><span class="s">{{ $periods[$period] }}</span></div>
        <div class="kpi"><span class="l">Actives cette semaine</span><span class="v">{{ $activeWeek }}</span><span class="s">au moins une connexion en 7 jours</span></div>
        <div class="kpi"><span class="l">Encaissé</span><span class="v" style="font-size:1.35rem">{{ $money($revenue) }}</span><span class="s">paiements validés · {{ $periods[$period] }}</span></div>
        <div class="kpi"><span class="l">Revenu mensuel (MRR)</span><span class="v" style="font-size:1.35rem">{{ $money($mrr) }}</span><span class="s">{{ $payingCount }} client(s) payant(s) · ARR {{ $money($arr) }}</span></div>
        <div class="kpi"><span class="l">Revenu moyen / client</span><span class="v" style="font-size:1.35rem">{{ $money($arpa) }}</span><span class="s">par mois</span></div>
        <div class="kpi"><span class="l">Désabonnement</span><span class="v">{{ number_format($churnRate, 1, ',', ' ') }} %</span><span class="s">{{ $churned }} client(s) payant(s) perdu(s)</span></div>
    </section>

    <section class="panel">
        <h2>À traiter</h2>
        @forelse($todo as $t)
            <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;padding:.55rem 0;border-bottom:1px solid var(--border)">
                <a href="{{ $t['url'] }}"><strong>{{ $t['label'] }}</strong></a>
                <span class="pill {{ $tone[$t['tone']] ?? '' }}">Voir →</span>
            </div>
        @empty
            <p class="muted" style="margin:0">Rien à traiter pour le moment.</p>
        @endforelse
    </section>

    <div class="two-col">
        <section class="panel">
            <h2>Encaissements <span class="muted small">{{ $periods[$period] }}</span></h2>
            @include('console._bars', ['data' => $series, 'format' => $money])
        </section>
        <section class="panel">
            <h2>Répartition par forfait</h2>
            @include('console._bars', ['data' => $byPlan->mapWithKeys(fn ($c, $id) => [($planNames[$id] ?? '—') => $c])])
        </section>
        <section class="panel">
            <h2>Entreprises par pays</h2>
            @include('console._bars', ['data' => $byCountry])
        </section>
        <section class="panel">
            <h2>Prochaines échéances</h2>
            @forelse($upcoming as $s)
                <div style="display:flex;justify-content:space-between;gap:1rem;padding:.45rem 0;border-bottom:1px solid var(--border)">
                    <span><a href="{{ route('console.tenants.show', $s->tenant) }}">{{ $s->tenant->name }}</a> <span class="muted small">· {{ $s->plan->name }}</span></span>
                    <span class="small {{ ($s->daysLeft() ?? 0) < 0 ? 'qty-neg' : 'muted' }}">{{ $s->endsAt()?->format('d/m/Y') }}</span>
                </div>
            @empty
                <p class="muted" style="margin:0">Aucune échéance.</p>
            @endforelse
        </section>
        <section class="panel">
            <h2>Entreprises récentes</h2>
            @forelse($recent as $t)
                <div style="display:flex;justify-content:space-between;gap:1rem;padding:.45rem 0;border-bottom:1px solid var(--border)">
                    <span><a href="{{ route('console.tenants.show', $t) }}"><strong>{{ $t->name }}</strong></a> <span class="muted small">· {{ $t->id }}</span></span>
                    <span class="muted small">{{ $t->created_at?->format('d/m/Y') }}</span>
                </div>
            @empty
                <p class="muted" style="margin:0">Aucune entreprise.</p>
            @endforelse
        </section>
        @if($admin->can('audit.view'))
            <section class="panel">
                <h2>Activité récente</h2>
                @forelse($activity as $l)
                    <div style="padding:.45rem 0;border-bottom:1px solid var(--border)">
                        <div class="small">{{ $l->description }}</div>
                        <div class="muted small">{{ $l->admin_name ?? 'Système' }} · {{ $l->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                @empty
                    <p class="muted" style="margin:0">Aucune activité.</p>
                @endforelse
                <p style="margin:.8rem 0 0"><a href="{{ route('console.audit.index') }}" class="small">Voir tout le journal →</a></p>
            </section>
        @endif
    </div>
@endsection
