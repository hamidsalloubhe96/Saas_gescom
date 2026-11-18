@extends('console.guest')
@section('title', 'Mot de passe oublié')
@section('content')
    <h1>Mot de passe oublié</h1>
    <p class="muted" style="margin-top:0">Saisissez votre e-mail : nous vous envoyons un code à 6 chiffres.</p>
    <form method="POST" action="{{ route('console.forgot.send') }}" novalidate>
        @csrf
        <div class="field">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn" style="width:100%">Envoyer le code</button>
    </form>
    <p style="margin-top:1rem"><a href="{{ route('console.login') }}">← Retour à la connexion</a></p>
@endsection
