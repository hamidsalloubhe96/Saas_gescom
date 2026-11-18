@extends('console.guest')
@section('title', 'Vérification')
@section('content')
    <h1>Double authentification</h1>
    <p class="muted" style="margin-top:0">Saisissez le code à 6 chiffres affiché par votre application d'authentification.</p>
    <form method="POST" action="{{ route('console.twofactor.verify') }}" novalidate>
        @csrf
        <div class="field">
            <label for="code">Code</label>
            <input id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus required>
        </div>
        <button class="btn" style="width:100%">Vérifier</button>
    </form>
    <p class="small muted" style="margin-bottom:0"><a href="{{ route('console.login') }}">← Retour à la connexion</a></p>
@endsection
