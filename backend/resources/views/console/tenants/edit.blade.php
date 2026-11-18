@extends('console.layout')
@section('title', 'Modifier ' . $tenant->name)
@section('content')
    <header class="page-head">
        <div>
            <h1>Modifier l'entreprise</h1>
            <p class="muted"><code>{{ $tenant->id }}</code> : l'identifiant ne peut pas être modifié.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('console.tenants.show', $tenant) }}">← Retour</a>
    </header>

    <form method="POST" action="{{ route('console.tenants.update', $tenant) }}" novalidate style="max-width:640px">
        @csrf @method('PUT')
        <section class="panel">
            <div class="field">
                <label for="company">Nom *</label>
                <input id="company" name="company" value="{{ old('company', $tenant->name) }}" required>
                @error('company')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="owner_email">E-mail du fondateur</label>
                <input id="owner_email" name="owner_email" type="email" value="{{ old('owner_email', $tenant->owner_email) }}">
                @error('owner_email')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="row">
                <div class="field"><label for="country">Pays</label><input id="country" name="country" value="{{ old('country', $tenant->country) }}"></div>
                <div class="field"><label for="city">Ville</label><input id="city" name="city" value="{{ old('city', $tenant->city) }}"></div>
            </div>
        </section>
        <button class="btn">Enregistrer</button>
    </form>
@endsection
