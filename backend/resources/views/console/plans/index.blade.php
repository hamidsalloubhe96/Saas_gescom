@extends('console.layout')
@section('title', 'Forfaits')
@section('content')
    <header class="page-head">
        <div>
            <h1>Forfaits</h1>
            <p class="muted">Les formules vendues : prix et plafonds. Un forfait désactivé n'est plus proposé aux nouveaux abonnés ; ceux qui l'ont le gardent.</p>
        </div>
        @if($admin->can('plans.manage'))<a class="btn" href="{{ route('console.plans.create') }}">+ Nouveau forfait</a>@endif
    </header>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Forfait</th><th class="num">Prix</th><th class="num">Utilisateurs</th><th class="num">Produits</th><th class="num">Factures / mois</th><th class="num">Points de vente</th><th class="num">Abonnés</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            @foreach($plans as $p)
                <tr>
                    <td><strong>{{ $p->name }}</strong> @if($p->is_trial)<span class="pill pill-info">Essai {{ $p->trial_days }} j</span>@endif
                        <div class="muted small"><code>{{ $p->slug }}</code>@if($p->description) · {{ $p->description }}@endif</div>
                        @if($p->tiers)<div class="muted small">Paliers : {{ collect($p->tiers)->map(fn ($t) => "≤ {$t['up_to']} : ".number_format($t['price'], 0, ',', ' '))->implode(' · ') }}</div>@endif</td>
                    <td class="num">{{ $p->is_trial ? 'Gratuit' : number_format($p->price, 0, ',', ' ').' '.$p->currency }}<div class="muted small">/ {{ $p->period_months }} mois</div></td>
                    <td class="num">{{ \App\Models\Plan::limitLabel($p->max_users) }}</td>
                    <td class="num">{{ \App\Models\Plan::limitLabel($p->max_products) }}</td>
                    <td class="num">{{ \App\Models\Plan::limitLabel($p->max_invoices_month) }}</td>
                    <td class="num">{{ \App\Models\Plan::limitLabel($p->max_branches) }}</td>
                    <td class="num">{{ $p->subscriptions_count }}</td>
                    <td><span class="pill {{ $p->is_active ? 'pill-ok' : '' }}">{{ $p->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                    <td style="white-space:nowrap">
                        @if($admin->can('plans.manage'))
                            <a class="btn btn-secondary btn-sm" href="{{ route('console.plans.edit', $p) }}">Modifier</a>
                            <form method="POST" action="{{ route('console.plans.toggle', $p) }}" style="display:inline">@csrf @method('PATCH')
                                <button class="btn btn-secondary btn-sm">{{ $p->is_active ? 'Désactiver' : 'Activer' }}</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
