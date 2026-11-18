@extends('console.layout')
@section('title', 'Textes légaux')
@section('content')
    <header class="page-head">
        <div><h1>Textes légaux</h1>
            <p class="muted">Enregistrez un brouillon, puis « Publier une nouvelle version ». Chaque publication crée une version numérotée ; les entreprises voient la version en vigueur.</p></div>
    </header>

    @foreach($texts as $key => $t)
        <details class="panel" @if($loop->first || $errors->has('body') && old('_key') === $key) open @endif>
            <summary style="font-size:1.05rem">
                {{ $t['label'] }}
                @if($t['current'])<span class="pill pill-ok">Version {{ $t['current']->version }} en vigueur</span>@else<span class="pill pill-warn">Non publié</span>@endif
            </summary>
            <div style="margin-top:.9rem">
                @if($t['current'])<p class="muted small">Publié le {{ $t['current']->published_at->format('d/m/Y H:i') }}. <a href="{{ url('/legal/'.$key) }}" target="_blank" rel="noopener">Voir la page publique</a></p>@endif
                <form method="POST" action="{{ route('console.legal.save', $key) }}" novalidate>
                    @csrf @method('PUT')
                    <input type="hidden" name="_key" value="{{ $key }}">
                    <div class="field"><label for="t-{{ $key }}">Titre</label><input id="t-{{ $key }}" name="title" value="{{ $t['draft']->title }}" maxlength="150" required></div>
                    <div class="field"><label for="b-{{ $key }}">Texte (séparez les paragraphes par une ligne vide)</label>
                        <textarea id="b-{{ $key }}" name="body" rows="12">{{ $t['draft']->body }}</textarea>
                        @if($errors->has('body') && old('_key') === $key)<p class="err">{{ $errors->first('body') }}</p>@endif</div>
                    <div class="actions-row">
                        <button class="btn btn-secondary">Enregistrer le brouillon</button>
                        <button class="btn" formaction="{{ route('console.legal.publish', $key) }}" onclick="return confirm('Publier ce texte ? Il remplacera la version en vigueur.')">Publier une nouvelle version</button>
                    </div>
                </form>
                @if($t['current'])
                    <form method="POST" action="{{ route('console.legal.reset', $key) }}" style="margin-top:.6rem">@csrf<button class="link-btn small" style="background:none;border:0;color:var(--accent);cursor:pointer">Remettre le brouillon à la version en vigueur</button></form>
                @endif
                @if($t['versions']->isNotEmpty())
                    <p class="muted small" style="margin:.8rem 0 0">Historique : {{ $t['versions']->map(fn ($v) => 'v'.$v->version.' ('.$v->published_at->format('d/m/Y').')')->implode(' · ') }}</p>
                @endif
            </div>
        </details>
    @endforeach
@endsection
