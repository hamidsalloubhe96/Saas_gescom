@extends('console.layout')
@section('title', 'Avis')
@php($stars = fn ($n) => str_repeat('★', $n).str_repeat('☆', 5 - $n))
@section('content')
    <header class="page-head">
        <div><h1>Avis</h1><p class="muted">Laissés par les utilisateurs. Un avis n'est visible sur le site qu'une fois publié.</p></div>
    </header>

    <section class="panel">
        <h2>À relire ({{ $pending->count() }})</h2>
        @forelse($pending as $r)
            <div style="padding:.8rem 0;border-bottom:1px solid var(--border)">
                <div><strong>{{ $r->author_name }}</strong> <span class="muted">· {{ $r->company }}</span> <span aria-label="{{ $r->rating }} sur 5" style="color:#d89b00">{{ $stars($r->rating) }}</span></div>
                <p style="margin:.35rem 0">{{ $r->body }}</p>
                <div class="actions-row">
                    <form method="POST" action="{{ route('console.reviews.update', $r) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="published"><button class="btn btn-sm">Publier</button></form>
                    <form method="POST" action="{{ route('console.reviews.update', $r) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="hidden"><button class="btn btn-secondary btn-sm">Masquer</button></form>
                    <span class="muted small">{{ $r->created_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>
        @empty
            <p class="muted" style="margin:0">Aucun avis à relire.</p>
        @endforelse
    </section>

    <h2 style="margin:1.4rem 0 .6rem">Déjà traités</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Auteur</th><th>Note</th><th>Avis</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            @forelse($handled as $r)
                <tr>
                    <td><strong>{{ $r->author_name }}</strong><div class="muted small">{{ $r->company }}</div></td>
                    <td style="color:#d89b00;white-space:nowrap">{{ $stars($r->rating) }}</td>
                    <td style="max-width:420px">{{ \Illuminate\Support\Str::limit($r->body, 200) }}</td>
                    <td><span class="pill {{ $r->status === 'published' ? 'pill-ok' : '' }}">{{ $r->status === 'published' ? 'Publié' : 'Masqué' }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('console.reviews.update', $r) }}">@csrf @method('PATCH')
                            <input type="hidden" name="status" value="{{ $r->status === 'published' ? 'hidden' : 'published' }}">
                            <button class="btn btn-secondary btn-sm">{{ $r->status === 'published' ? 'Masquer' : 'Publier' }}</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Aucun avis traité.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('console._pager', ['paginator' => $handled])
@endsection
