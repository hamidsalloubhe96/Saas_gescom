@extends('console.layout')
@section('title', 'Annonces')
@php($tone = ['info' => 'pill-info', 'warning' => 'pill-warn', 'update' => 'pill-ok'])
@section('content')
    <header class="page-head">
        <div><h1>Annonces</h1><p class="muted">Messages affichés dans le tableau de bord de TOUTES les entreprises.</p></div>
    </header>

    <section class="panel" style="max-width:760px">
        <h2>Nouvelle annonce</h2>
        <form method="POST" action="{{ route('console.announcements.store') }}" novalidate>
            @csrf
            <div class="row">
                <div class="field"><label for="title">Titre *</label><input id="title" name="title" maxlength="150" value="{{ old('title') }}" required>@error('title')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="level">Type</label><select id="level" name="level">@foreach(\App\Models\Announcement::LEVELS as $k => $l)<option value="{{ $k }}" @selected(old('level') === $k)>{{ $l }}</option>@endforeach</select></div>
            </div>
            <div class="field"><label for="body">Message</label><textarea id="body" name="body" rows="3" maxlength="1000">{{ old('body') }}</textarea>@error('body')<p class="err">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="ends_at">Masquer automatiquement le (optionnel)</label><input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at') }}">@error('ends_at')<p class="err">{{ $message }}</p>@enderror</div>
            <button class="btn">Publier</button>
        </form>
    </section>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Annonce</th><th>Type</th><th>Fin</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            @forelse($announcements as $a)
                <tr>
                    <td><strong>{{ $a->title }}</strong>@if($a->body)<div class="muted small">{{ \Illuminate\Support\Str::limit($a->body, 140) }}</div>@endif</td>
                    <td><span class="pill {{ $tone[$a->level] }}">{{ \App\Models\Announcement::LEVELS[$a->level] }}</span></td>
                    <td>{{ $a->ends_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td><span class="pill {{ $a->is_active ? 'pill-ok' : '' }}">{{ $a->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td style="white-space:nowrap">
                        <form method="POST" action="{{ route('console.announcements.toggle', $a) }}" style="display:inline">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm">{{ $a->is_active ? 'Désactiver' : 'Activer' }}</button></form>
                        <form method="POST" action="{{ route('console.announcements.destroy', $a) }}" style="display:inline" onsubmit="return confirm('Supprimer cette annonce ?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm"><x-icon name="delete" class="size-4" />Supprimer</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Aucune annonce.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('console._pager', ['paginator' => $announcements])
@endsection
