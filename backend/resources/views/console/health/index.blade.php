@extends('console.layout')
@section('title', 'Santé')
@php
    $bytes = fn ($b) => $b === null ? '—' : ($b >= 1073741824 ? number_format($b / 1073741824, 1, ',', ' ').' Go' : number_format($b / 1048576, 1, ',', ' ').' Mo');
    $tone = ['bad' => 'alert-bad', 'warn' => 'alert-warn', 'info' => ''];
    $taskLabels = ['abonnements' => 'Abonnements (retards, expirations)', 'entreprises-abandonnees' => 'Entreprises abandonnées', 'purge-entreprises' => 'Suppression des entreprises échues',
        'nettoyage-journal' => 'Nettoyage du journal', 'taches-complementaires' => 'Tâches complémentaires', 'sauvegarde-base' => 'Sauvegarde de la base'];
@endphp
@section('content')
    <header class="page-head">
        <div><h1>Santé de l'application</h1><p class="muted">État technique à l'ouverture de la page. Les mises à jour se déploient depuis Render (déploiement automatique à chaque commit).</p></div>
    </header>

    @error('health')<div class="alert alert-bad" role="alert">{{ $message }}</div>@enderror

    @foreach($warnings as [$level, $text])
        <div class="alert {{ $tone[$level] }}" role="alert" style="{{ $level === 'info' ? 'background:var(--accent-soft);color:var(--text)' : '' }}">{{ $text }}</div>
    @endforeach
    @if($taskDelayed)
        <div class="alert alert-bad" role="alert">Les tâches du matin n'ont pas tourné depuis plus de 26 h ({{ implode(', ', $taskDelayed) }}). Vérifiez que le planificateur est actif.</div>
    @endif

    <section class="kpis" aria-label="Indicateurs techniques">
        <div class="kpi"><span class="l">Base de données</span><span class="v" style="font-size:1.35rem">{{ $system['db_ms'] !== null ? $system['db_ms'].' ms' : 'Hors service' }}</span><span class="s">{{ $bytes($system['db_size']) }} · {{ $system['schemas'] ?? '—' }} schéma(s) d'entreprise</span></div>
        <div class="kpi"><span class="l">Tâches échouées (file)</span><span class="v" style="{{ ($system['failed_jobs'] ?? 0) > 0 ? 'color:var(--danger)' : '' }}">{{ $system['failed_jobs'] ?? '—' }}</span><span class="s">{{ $system['pending_jobs'] ?? '—' }} en attente</span></div>
        <div class="kpi"><span class="l">Espace disque libre</span><span class="v" style="font-size:1.35rem">{{ $bytes($system['disk_free']) }}</span></div>
        <div class="kpi"><span class="l">Environnement</span><span class="v" style="font-size:1.35rem">{{ $system['env'] }}</span><span class="s">PHP {{ $system['php'] }} · Laravel {{ $system['laravel'] }}</span></div>
    </section>

    <div class="two-col">
        <section class="panel">
            <h2>Tâches automatiques</h2>
            <p class="muted small" style="margin-top:0">Chaque matin à 6 h. Chaque tâche est isolée : si l'une échoue, les suivantes tournent quand même.</p>
            @forelse($lastRuns as $name => $r)
                <div style="display:flex;justify-content:space-between;gap:1rem;padding:.45rem 0;border-bottom:1px solid var(--border)">
                    <span>{{ $taskLabels[$name] ?? $name }}<div class="muted small">{{ $r->message }}</div></span>
                    <span style="text-align:right"><span class="pill {{ $r->status === 'ok' ? 'pill-ok' : 'pill-bad' }}">{{ $r->status === 'ok' ? 'OK' : 'Échec' }}</span><div class="muted small">{{ $r->ran_at->format('d/m H:i') }} · {{ $r->duration_ms }} ms</div></span>
                </div>
            @empty
                <p class="muted" style="margin:0">Aucune exécution enregistrée. Lancez <code>php artisan console:daily</code> ou attendez 6 h.</p>
            @endforelse
        </section>

        <section class="panel">
            <h2>Ce que vous devez faire</h2>
            @foreach($reminders as $r)
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;padding:.55rem 0;border-bottom:1px solid var(--border)">
                    <span>{{ $r['label'] }}
                        <div class="small {{ $r['due'] ? 'qty-neg' : 'muted' }}">{{ $r['done_at'] ? 'Fait le '.$r['done_at']->format('d/m/Y').' · tous les '.$r['every'].' j' : 'Jamais fait' }}{{ $r['due'] ? ' — à refaire' : '' }}</div></span>
                    <form method="POST" action="{{ route('console.health.note') }}">@csrf<input type="hidden" name="key" value="{{ $r['key'] }}"><button class="btn btn-secondary btn-sm">Noter comme fait</button></form>
                </div>
            @endforeach
        </section>
    </div>

    <section class="panel">
        <h2>Vérifier que les alertes arrivent</h2>
        <p class="muted" style="margin-top:0">Adresse d'alerte : <strong>{{ config('console.alert_email') ?: 'non configurée (CONSOLE_ALERT_EMAIL)' }}</strong>. Un vrai message de test est envoyé pour être sûr que vous serez prévenu en cas de problème.</p>
        <div class="actions-row">
            <form method="POST" action="{{ route('console.health.test-error') }}">@csrf<button class="btn btn-secondary" @disabled(! $alertEnabled)>Tester l'alerte d'erreurs</button></form>
            <form method="POST" action="{{ route('console.health.test-email') }}">@csrf<button class="btn btn-secondary">Tester l'envoi d'e-mail</button></form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem">
            <h2 style="margin:0">Migrations des entreprises <span class="muted small">({{ $migrationFiles }} migration(s) attendue(s))</span></h2>
            <form method="POST" action="{{ route('console.health.migrate-all') }}">@csrf<button class="btn btn-secondary btn-sm">Tout migrer (arrière-plan)</button></form>
        </div>
        <div class="table-wrap" style="border:0">
            <table>
                <thead><tr><th>Entreprise</th><th class="num">Appliquées</th><th>État</th><th></th></tr></thead>
                <tbody>
                @foreach($migrations as $m)
                    <tr>
                        <td><a href="{{ route('console.tenants.show', $m['tenant']) }}">{{ $m['tenant']->name }}</a> <code class="small">{{ $m['tenant']->id }}</code></td>
                        <td class="num">{{ $m['applied'] ?? '—' }}</td>
                        <td>@if($m['error'])<span class="pill pill-bad">{{ $m['error'] }}</span>@elseif($m['pending'] === 0)<span class="pill pill-ok">À jour</span>@else<span class="pill pill-warn">{{ $m['pending'] }} en attente</span>@endif</td>
                        <td>@if($m['pending'])<form method="POST" action="{{ route('console.health.migrate', $m['tenant']) }}">@csrf<button class="btn btn-secondary btn-sm">Migrer</button></form>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem">
            <h2 style="margin:0">Sauvegardes de la base</h2>
            <form method="POST" action="{{ route('console.health.backup') }}">@csrf<button class="btn btn-secondary btn-sm">Sauvegarder maintenant</button></form>
        </div>
        @forelse($backups as $b)
            <div class="small" style="padding:.35rem 0;border-bottom:1px solid var(--border)">{{ $b['at']->format('d/m/Y H:i') }} · {{ $bytes($b['size']) }} · <a href="{{ route('console.health.download', ['path' => $b['path']]) }}">{{ $b['name'] }}</a></div>
        @empty
            <p class="muted" style="margin:0">Aucune sauvegarde sur le disque configuré ({{ config('console.backup_disk') }}). Les exports par entreprise se trouvent sur la fiche de chaque entreprise.</p>
        @endforelse
    </section>

    <section class="panel">
        <h2>Dernières exécutions</h2>
        <div class="table-wrap" style="border:0">
            <table>
                <thead><tr><th>Date</th><th>Tâche</th><th>Résultat</th><th class="num">Durée</th></tr></thead>
                <tbody>
                @forelse($runs as $r)
                    <tr><td>{{ $r->ran_at->format('d/m/Y H:i:s') }}</td><td>{{ $taskLabels[$r->name] ?? $r->name }}</td>
                        <td><span class="pill {{ $r->status === 'ok' ? 'pill-ok' : 'pill-bad' }}">{{ $r->status === 'ok' ? 'OK' : 'Échec' }}</span> <span class="muted small">{{ $r->message }}</span></td><td class="num">{{ $r->duration_ms }} ms</td></tr>
                @empty
                    <tr><td colspan="4" class="empty">Aucune exécution.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
