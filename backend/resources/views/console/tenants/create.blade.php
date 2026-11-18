@extends('console.layout')
@section('title', 'Créer une entreprise')
@section('content')
    <header class="page-head">
        <div>
            <h1>Créer une entreprise</h1>
            <p class="muted">Son espace de données (schéma PostgreSQL) est créé et préparé automatiquement.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('console.tenants.index') }}">← Retour</a>
    </header>

    <form method="POST" action="{{ route('console.tenants.store') }}" novalidate style="max-width:720px">
        @csrf
        @if($prefill)<input type="hidden" name="request_id" value="{{ $prefill->id }}">
            <div class="alert alert-warn">Créée à partir de la demande de <strong>{{ $prefill->name }}</strong> ({{ $prefill->email }}).</div>@endif
        <section class="panel">
            <h2>Entreprise</h2>
            <div class="field">
                <label for="company">Nom de l'entreprise *</label>
                <input id="company" name="company" value="{{ old('company', $prefill?->company) }}" required>
                @error('company')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="slug">Identifiant *</label>
                <input id="slug" name="slug" value="{{ old('slug') }}" autocapitalize="none" required>
                <p class="hint">3 à 30 caractères : minuscules, chiffres, _ (commence par une lettre). Sert à la connexion des utilisateurs, non modifiable ensuite.</p>
                @error('slug')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="row">
                <div class="field"><label for="country">Pays</label><input id="country" name="country" value="{{ old('country', $prefill?->country) }}">@error('country')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="city">Ville</label><input id="city" name="city" value="{{ old('city') }}">@error('city')<p class="err">{{ $message }}</p>@enderror</div>
            </div>
        </section>
        <section class="panel">
            <h2>Fondateur (premier administrateur)</h2>
            <div class="row">
                <div class="field"><label for="name">Nom *</label><input id="name" name="name" value="{{ old('name', $prefill?->name) }}" required>@error('name')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="email">E-mail *</label><input id="email" name="email" type="email" value="{{ old('email', $prefill?->email) }}" required>@error('email')<p class="err">{{ $message }}</p>@enderror</div>
            </div>
            <label style="display:flex;gap:.5rem;align-items:center;margin-bottom:.6rem"><input type="checkbox" name="send_email" value="1" checked> Envoyer ses accès par e-mail</label>
            <p class="hint">Un mot de passe provisoire est généré (à changer à la première connexion) et affiché une seule fois ici, que l'e-mail soit envoyé ou non.</p>
        </section>
        <button class="btn">Créer l'entreprise</button>
    </form>
@endsection
