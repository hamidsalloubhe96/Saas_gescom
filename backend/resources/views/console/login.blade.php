@extends('console.guest')
@section('title', 'Connexion')
@push('head')
    @if($turnstileKey)<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endif
@endpush
@section('content')
    <h1>Connexion</h1>
    <p class="muted" style="margin-top:0">Réservé à l'équipe de la plateforme.</p>
    <form method="POST" action="{{ route('console.login.submit') }}" novalidate class="mt-6">
        @csrf
        <div class="field">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="vous@exemple.com" autocomplete="username" required autofocus>
            @error('email')<p class="err">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" placeholder="Votre mot de passe" autocomplete="current-password" required>
        </div>
        @if($turnstileKey)
            <div class="cf-turnstile" data-sitekey="{{ $turnstileKey }}" style="margin-bottom:.9rem"></div>
        @endif
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1" class="size-4 accent-[var(--accent)]"> Se souvenir de moi</label>
            <a href="{{ route('console.forgot') }}" class="font-semibold">Mot de passe oublié ?</a>
        </div>
        <button class="btn w-full">Se connecter</button>
    </form>
    @php($app = rtrim((string) config('app.frontend_url'), '/'))
    <p class="mt-5 text-center text-sm text-muted">Vous gérez une entreprise ? <a href="{{ $app ? $app.'/login' : url('/login') }}" class="font-semibold">Accéder à votre espace</a></p>
    <p class="mt-3 text-center text-sm"><a href="{{ $app ?: url('/') }}" class="text-muted no-underline hover:underline">Retour au site</a></p>
@endsection
