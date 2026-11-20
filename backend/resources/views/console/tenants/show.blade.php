@extends('console.layout')
@section('title', $tenant->name)
@php
    $tone = ['active' => 'pill-ok', 'suspended' => 'pill-warn', 'banned' => 'pill-bad'];
    $fmtBytes = fn ($b) => $b === null ? '—' : ($b >= 1048576 ? number_format($b / 1048576, 1, ',', ' ').' Mo' : number_format($b / 1024, 0, ',', ' ').' Ko');
@endphp
@section('content')
    <header class="page-head">
        <div>
            <h1>{{ $tenant->name }} <span class="pill {{ $tone[$tenant->status] ?? '' }}">{{ $tenant->statusLabel() }}</span></h1>
            <p class="muted"><code>{{ $tenant->id }}</code> · créée le {{ $tenant->created_at?->format('d/m/Y') }}</p>
        </div>
        <div class="actions-row">
            @if($admin->can('tenants.update'))<a class="btn btn-secondary" href="{{ route('console.tenants.edit', $tenant) }}">Modifier</a>@endif
            @if($admin->can('users.view'))<a class="btn btn-secondary" href="{{ route('console.users.index', ['tenant' => $tenant->id]) }}">Utilisateurs</a>@endif
            <a class="btn btn-secondary" href="{{ route('console.tenants.index') }}">← Entreprises</a>
        </div>
    </header>

    @unless($stats['available'])
        <div class="alert alert-bad" role="alert">Impossible de lire l'espace de cette entreprise (schéma absent ou migrations en attente).</div>
    @endunless

    <div class="grid grid-2">
        <section class="panel">
            <h2>Identité</h2>
            <dl class="kv">
                <dt>Nom</dt><dd>{{ $tenant->name }}</dd>
                <dt>Identifiant</dt><dd><code>{{ $tenant->id }}</code></dd>
                <dt>Fondateur</dt><dd>{{ $tenant->owner_email ?? '—' }}</dd>
                <dt>Pays / ville</dt><dd>{{ collect([$tenant->country, $tenant->city])->filter()->implode(' / ') ?: '—' }}</dd>
                <dt>Type d'activité</dt><dd>{{ \App\Models\Tenant::BUSINESS_TYPES[$tenant->business_type ?? 'general'] ?? $tenant->business_type }}</dd>
                <dt>Dernière connexion</dt><dd>{{ $tenant->last_activity_at?->format('d/m/Y H:i') ?? 'Jamais' }}</dd>
                <dt>Taille des données</dt><dd>{{ $fmtBytes($stats['bytes']) }}</dd>
            </dl>
        </section>

        <section class="panel">
            <h2>Volumes</h2>
            @if($stats['counts'])
                <dl class="kv">
                    @foreach($stats['counts'] as $label => $n)<dt>{{ $label }}</dt><dd>{{ $n }}</dd>@endforeach
                    <dt>Utilisateurs</dt><dd>{{ $stats['users']->count() }}</dd>
                </dl>
                <p class="muted small" style="margin-bottom:0">La console ne montre que des volumes, jamais le contenu des ventes ou des clients.</p>
            @else
                <p class="muted">Indisponible.</p>
            @endif
        </section>
    </div>

    @if($tenant->status !== 'active')
        <div class="alert alert-warn" role="alert">
            <strong>{{ $tenant->status === 'banned' ? 'Bannie' : 'Suspendue' }}</strong>
            depuis le {{ $tenant->suspended_at?->format('d/m/Y H:i') }} : {{ $tenant->suspended_reason }}
        </div>
    @endif

    @php($left = $sub?->daysLeft())
    <section class="panel">
        <h2>Abonnement
            @if($sub)<span class="pill {{ ['trial' => 'pill-info', 'active' => 'pill-ok', 'past_due' => 'pill-warn', 'expired' => 'pill-bad'][$sub->status] ?? '' }}">{{ $sub->statusLabel() }}</span>@endif
        </h2>
        @if($sub)
            <dl class="kv" style="margin-bottom:1rem">
                <dt>Forfait</dt><dd>{{ $sub->plan->name }} @if(! $sub->plan->is_trial)· {{ number_format($sub->plan->price, 0, ',', ' ') }} {{ $sub->plan->currency }} / {{ $sub->plan->period_months }} mois @endif</dd>
                <dt>{{ $sub->status === 'trial' ? 'Fin de l\'essai' : 'Prochaine échéance' }}</dt>
                <dd>{{ $sub->endsAt()?->format('d/m/Y') ?? '—' }} @if($left !== null)<span class="muted">({{ $left < 0 ? 'en retard de '.abs($left).' j' : $left.' j restants' }})</span>@endif</dd>
                @if($sub->status === 'past_due' && $sub->grace_ends_at)<dt>Coupure automatique</dt><dd>{{ $sub->grace_ends_at->format('d/m/Y') }}</dd>@endif
                @if($sub->custom_limits)<dt>Limites</dt><dd>Personnalisées (voir « Limites »)</dd>@endif
            </dl>
        @endif

        @if($admin->can('subscriptions.manage') || $admin->can('payments.validate'))
            <div class="actions-row">
                @if($admin->can('payments.validate'))
                    <details style="min-width:300px"><summary>Encaisser un paiement hors ligne</summary>
                        <form method="POST" action="{{ route('console.tenants.billing.payment', $tenant) }}" style="margin-top:.6rem">@csrf
                            <div class="row">
                                <div class="field"><label for="pp">Forfait</label><select id="pp" name="plan_id">@foreach($plans as $p)<option value="{{ $p->id }}" @selected($sub?->plan_id === $p->id)>{{ $p->name }} ({{ number_format($p->price, 0, ',', ' ') }})</option>@endforeach</select></div>
                                <div class="field"><label for="pm">Périodes</label><input id="pm" name="months" type="number" min="1" max="24" value="1"></div>
                            </div>
                            <div class="row">
                                <div class="field"><label for="pa">Montant reçu *</label><input id="pa" name="amount" type="number" min="1" step="1" required></div>
                                <div class="field"><label for="pme">Mode</label><select id="pme" name="method">@foreach(\App\Models\SubscriptionPayment::METHODS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                            </div>
                            <div class="field"><label for="pr">Référence</label><input id="pr" name="reference" maxlength="190"></div>
                            <button class="btn btn-sm">Encaisser</button>
                        </form>
                    </details>
                @endif
                @if($admin->can('subscriptions.manage'))
                    <details style="min-width:260px"><summary>Activer / prolonger</summary>
                        <form method="POST" action="{{ route('console.tenants.billing.activate', $tenant) }}" style="margin-top:.6rem">@csrf
                            <div class="row">
                                <div class="field"><label for="ap">Forfait</label><select id="ap" name="plan_id">@foreach($plans as $p)<option value="{{ $p->id }}" @selected($sub?->plan_id === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                                <div class="field"><label for="am">Périodes</label><input id="am" name="months" type="number" min="1" max="24" value="1"></div>
                            </div>
                            <button class="btn btn-sm">Activer</button>
                        </form>
                    </details>
                    <details style="min-width:220px"><summary>Offrir des jours</summary>
                        <form method="POST" action="{{ route('console.tenants.billing.offer', $tenant) }}" style="margin-top:.6rem">@csrf
                            <div class="field"><label for="od">Nombre de jours</label><input id="od" name="days" type="number" min="1" max="365" value="7"></div>
                            <button class="btn btn-sm">Offrir</button>
                        </form>
                    </details>
                    <details style="min-width:220px"><summary>Changer de forfait</summary>
                        <form method="POST" action="{{ route('console.tenants.billing.plan', $tenant) }}" style="margin-top:.6rem">@csrf
                            <div class="field"><select name="plan_id" aria-label="Forfait">@foreach(\App\Models\Plan::orderBy('sort')->get() as $p)<option value="{{ $p->id }}" @selected($sub?->plan_id === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                            <button class="btn btn-sm">Changer</button>
                        </form>
                    </details>
                    @if($sub && $sub->status !== 'cancelled')
                        <details class="danger" style="min-width:260px"><summary>Résilier l'abonnement</summary>
                            <p class="small">L'accès est fermé jusqu'à un nouveau paiement.</p>
                            <form method="POST" action="{{ route('console.tenants.billing.cancel', $tenant) }}">@csrf
                                <div class="field"><label for="cc">Tapez « {{ $tenant->id }} » pour confirmer</label><input id="cc" name="confirm" autocomplete="off" required></div>
                                <button class="btn btn-danger btn-sm">Résilier</button>
                            </form>
                        </details>
                    @endif
                @endif
            </div>
        @endif

        @if($payments->isNotEmpty())
            <h3 style="margin:1rem 0 .4rem;font-size:.95rem">Derniers paiements</h3>
            @foreach($payments as $p)
                <div style="padding:.35rem 0;border-bottom:1px solid var(--border)" class="small">
                    {{ $p->created_at->format('d/m/Y') }} · <strong>{{ number_format((float) $p->amount, 0, ',', ' ') }} {{ $p->currency }}</strong> · {{ $p->methodLabel() }}
                    · <span class="pill {{ ['validated' => 'pill-ok', 'pending' => 'pill-warn', 'rejected' => 'pill-bad'][$p->status] }}">{{ ['validated' => 'Validé', 'pending' => 'À valider', 'rejected' => 'Refusé'][$p->status] }}</span>
                </div>
            @endforeach
        @endif
    </section>

    @if($stats['branches']->isNotEmpty())
    @php($extra = $stats['branches']->where('is_main', false)->where('is_active', true)->count())
    <section class="panel">
        <h2>Points de vente
            <span class="muted small">{{ $extra }} point{{ $extra > 1 ? 's' : '' }} de vente hors siège · limite : {{ \App\Models\Plan::limitLabel($stats['branch_limit']) }}</span></h2>
        <div class="table-wrap" style="border:0">
            <table>
                <thead><tr><th>Nom</th><th>Zone</th><th class="num">Ventes du mois</th><th class="num">Valeur du stock</th><th>Statut</th></tr></thead>
                <tbody>
                @foreach($stats['branches'] as $b)
                    <tr>
                        <td><strong>{{ $b->name }}</strong> @if($b->is_main)<span class="pill pill-info">Siège</span>@endif<div class="muted small"><code>{{ $b->code }}</code></div></td>
                        <td>{{ collect([$b->city, $b->region])->filter()->implode(', ') ?: '—' }}</td>
                        <td class="num">{{ number_format($b->sales_month, 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($b->stock_value, 0, ',', ' ') }}</td>
                        <td><span class="pill {{ $b->is_active ? 'pill-ok' : '' }}">{{ $b->is_active ? 'Actif' : 'Inactif' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif

    <section class="panel">
        <h2>Utilisateurs de l'entreprise</h2>
        <div class="table-wrap" style="border:0">
            <table>
                <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th>Créé le</th></tr></thead>
                <tbody>
                @forelse($stats['users'] as $u)
                    <tr>
                        <td>{{ $u->name }}</td><td>{{ $u->email }}</td>
                        <td>{{ config("permissions.roles.{$u->role}.label", $u->role) }}</td>
                        <td><span class="pill {{ $u->is_active ? 'pill-ok' : '' }}">{{ $u->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                        <td>{{ \Illuminate\Support\Carbon::parse($u->created_at)->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Aucun utilisateur.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($admin->can('tenants.suspend') || $admin->can('tenants.ban'))
        <section class="panel">
            <h2>Accès</h2>
            <div class="actions-row">
                @if($tenant->status !== 'active' && $admin->can('tenants.suspend'))
                    <form method="POST" action="{{ route('console.tenants.reactivate', $tenant) }}"
                          onsubmit="return confirm('Rétablir l\'accès de cette entreprise ?')">@csrf
                        <button class="btn">Rétablir l'accès</button>
                    </form>
                @endif

                @if($tenant->status === 'active' && $admin->can('tenants.suspend'))
                    <details class="danger" style="min-width:300px">
                        <summary>Suspendre l'accès</summary>
                        <p class="small">Ses utilisateurs ne pourront plus se connecter. Les données sont conservées.</p>
                        <form method="POST" action="{{ route('console.tenants.suspend', $tenant) }}">@csrf
                            <div class="field"><label for="s-reason">Motif *</label><input id="s-reason" name="reason" maxlength="255" required></div>
                            <div class="field"><label for="s-confirm">Tapez « {{ $tenant->id }} » pour confirmer</label><input id="s-confirm" name="confirm" autocomplete="off" required></div>
                            <button class="btn btn-danger">Suspendre</button>
                        </form>
                    </details>
                @endif

                @if($tenant->status !== 'banned' && $admin->can('tenants.ban'))
                    <details class="danger" style="min-width:300px">
                        <summary>Bannir cette entreprise</summary>
                        <p class="small">Sortie de la plateforme : à réserver aux cas graves. Réversible uniquement par « Rétablir l'accès ».</p>
                        <form method="POST" action="{{ route('console.tenants.ban', $tenant) }}">@csrf
                            <div class="field"><label for="b-reason">Motif *</label><input id="b-reason" name="reason" maxlength="255" required></div>
                            <div class="field"><label for="b-confirm">Tapez « {{ $tenant->id }} » pour confirmer</label><input id="b-confirm" name="confirm" autocomplete="off" required></div>
                            <button class="btn btn-danger">Bannir</button>
                        </form>
                    </details>
                @endif
            </div>
        </section>
    @endif

    @if($admin->can('tenants.delete'))
        @php($exports = app(\App\Services\TenantExporter::class)->list($tenant))
        <section class="panel">
            <h2>Données et fin de vie
                @if($tenant->is_protected)<span class="pill pill-info">Protégée</span>@endif
                @if($tenant->deletion_scheduled_at)<span class="pill pill-bad">Suppression le {{ $tenant->deletion_scheduled_at->format('d/m/Y') }}</span>@endif
            </h2>
            @error('lifecycle')<div class="alert alert-bad" role="alert">{{ $message }}</div>@enderror

            @if($tenant->deletion_scheduled_at)
                <div class="alert alert-warn">La suppression définitive aura lieu le <strong>{{ $tenant->deletion_scheduled_at->format('d/m/Y') }}</strong>. Un export a été pris. Vous pouvez l'annuler d'ici là.
                    @if($tenant->suspension_source === 'subscription') Si l'entreprise règle son abonnement, la suppression est annulée automatiquement.@endif</div>
                <form method="POST" action="{{ route('console.tenants.lifecycle.cancel', $tenant) }}" style="margin-bottom:1rem">@csrf<button class="btn">Annuler la suppression</button></form>
            @endif

            <div class="actions-row">
                <form method="POST" action="{{ route('console.tenants.lifecycle.export', $tenant) }}">@csrf<button class="btn btn-secondary">Exporter les données maintenant</button></form>
                <form method="POST" action="{{ route('console.tenants.lifecycle.protect', $tenant) }}">@csrf
                    <input type="hidden" name="protected" value="{{ $tenant->is_protected ? 0 : 1 }}">
                    <button class="btn btn-secondary">{{ $tenant->is_protected ? 'Retirer la protection' : 'Protéger de la suppression automatique' }}</button></form>
                @if(! $tenant->deletion_scheduled_at && ! $tenant->is_protected)
                    <details class="danger" style="min-width:300px"><summary>Programmer la suppression…</summary>
                        <p class="small">Un export est pris d'abord, l'entreprise est fermée, puis supprimée définitivement après {{ \App\Models\PlatformSetting::int('deletion_grace_days', 30) }} jours. Annulable d'ici là.</p>
                        <form method="POST" action="{{ route('console.tenants.lifecycle.schedule', $tenant) }}">@csrf
                            <div class="field"><label for="d-reason">Motif *</label><input id="d-reason" name="reason" maxlength="200" required></div>
                            <div class="field"><label for="d-confirm">Tapez « {{ $tenant->id }} » pour confirmer</label><input id="d-confirm" name="confirm" autocomplete="off" required></div>
                            <button class="btn btn-danger">Programmer la suppression</button>
                        </form>
                    </details>
                @endif
            </div>

            @if($exports)
                <h3 style="margin:1rem 0 .4rem;font-size:.95rem">Exports disponibles</h3>
                @foreach(array_slice($exports, 0, 5) as $e)
                    <div style="padding:.35rem 0;border-bottom:1px solid var(--border)" class="small">
                        {{ $e['at']->format('d/m/Y H:i') }} · {{ number_format($e['size'] / 1024, 0, ',', ' ') }} Ko ·
                        <a href="{{ route('console.tenants.lifecycle.download', [$tenant, 'file' => $e['name']]) }}">Télécharger</a>
                        <span class="muted">· restauration : <code>php artisan tenants:restore {{ $tenant->id }} {{ $e['path'] }}</code></span>
                    </div>
                @endforeach
            @endif
        </section>
    @endif

    <section class="panel">
        <h2>Historique de cette entreprise</h2>
        @forelse($audit as $l)
            <div style="padding:.5rem 0;border-bottom:1px solid var(--border)">
                <div class="small">{{ $l->description }}</div>
                <div class="muted small">{{ $l->admin_name ?? '—' }} · {{ $l->created_at?->format('d/m/Y H:i') }}</div>
            </div>
        @empty
            <p class="muted" style="margin:0">Aucune action enregistrée.</p>
        @endforelse
    </section>
@endsection
