@extends('console.layout')
@section('title', 'Mon compte')
@section('content')
    <header class="page-head">
        <div>
            <h1>Mon compte</h1>
            <p class="muted">{{ auth('admin')->user()->email }} · {{ auth('admin')->user()->roleLabel() }}</p>
        </div>
    </header>

    <section class="panel" style="max-width:520px">
        <h2>Changer mon mot de passe</h2>
        <form method="POST" action="{{ route('console.password.update') }}" novalidate>
            @csrf @method('PUT')
            <div class="field"><label for="cp">Mot de passe actuel</label><input id="cp" name="current_password" type="password" autocomplete="current-password" required></div>
            <div class="field">
                <label for="np">Nouveau mot de passe</label>
                <input id="np" name="password" type="password" autocomplete="new-password" required>
                <p class="hint">10 caractères minimum, avec des lettres et des chiffres.</p>
            </div>
            <div class="field"><label for="npc">Confirmation</label><input id="npc" name="password_confirmation" type="password" autocomplete="new-password" required></div>
            <button class="btn">Enregistrer</button>
        </form>
    </section>

    <section class="panel" style="max-width:520px">
        <h2>Double authentification
            @if(auth('admin')->user()->two_factor_confirmed_at)<span class="pill pill-ok">Activée</span>@else<span class="pill pill-warn">Non activée</span>@endif
        </h2>
        <a class="btn btn-secondary" href="{{ route('console.twofactor.setup') }}">Gérer</a>
    </section>
@endsection
