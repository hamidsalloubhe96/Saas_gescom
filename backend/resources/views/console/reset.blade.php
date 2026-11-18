@extends('console.guest')
@section('title', 'Nouveau mot de passe')
@section('content')
    <h1>Nouveau mot de passe</h1>
    <p class="muted" style="margin-top:0">Code envoyé à <strong>{{ $email }}</strong>.</p>
    <form method="POST" action="{{ route('console.reset.submit') }}" novalidate>
        @csrf
        <div class="field">
            <label for="code">Code à 6 chiffres</label>
            <input id="code" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus>
            @error('code')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="password">Nouveau mot de passe</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            <p class="muted small" style="margin:.25rem 0 0">10 caractères minimum, avec lettres et chiffres.</p>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Confirmation</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
        <button class="btn" style="width:100%">Changer le mot de passe</button>
    </form>
    <p style="margin-top:1rem"><a href="{{ route('console.forgot') }}">Renvoyer un code</a> · <a href="{{ route('console.login') }}">Connexion</a></p>
@endsection
