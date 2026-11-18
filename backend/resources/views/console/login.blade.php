@extends('console.guest')
@section('title', 'Connexion')
@push('head')
    @if($turnstileKey)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
@endpush
@section('content')
    <h1>Connexion</h1>
    <p class="muted" style="margin-top:0">Espace réservé à l'équipe de la plateforme.</p>
    <form method="POST" action="{{ route('console.login.submit') }}" novalidate>
        @csrf
        <div class="field">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        @if($turnstileKey)
            <div class="cf-turnstile" data-sitekey="{{ $turnstileKey }}" style="margin-bottom:.9rem"></div>
        @endif
        <button class="btn" style="width:100%">Se connecter</button>
    </form>
    <p style="margin-top:1rem"><a href="{{ route('console.forgot') }}">Mot de passe oublié ?</a></p>
@endsection
