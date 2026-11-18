@extends('console.layout')
@section('title', 'Double authentification')
@section('content')
    <div class="mx-auto w-full max-w-xl">
    <header class="page-head">
        <div>
            <h1>Double authentification</h1>
            <p class="muted">Un second code, généré par votre téléphone, protège votre compte même si votre mot de passe fuit.</p>
        </div>
    </header>

    @if($enabled)
        <section class="panel">
            <h2>Activée <span class="pill pill-ok">Oui</span></h2>
            <p class="muted">Un code vous est demandé à chaque connexion.</p>
            @if(! auth('admin')->user()->isSuper())
                <details class="danger">
                    <summary>Désactiver la double authentification</summary>
                    <form method="POST" action="{{ route('console.twofactor.setup.disable') }}" style="margin-top:.8rem">
                        @csrf @method('DELETE')
                        <div class="field"><label for="cp">Mot de passe actuel</label><input id="cp" name="current_password" type="password" required></div>
                        <button class="btn btn-danger">Désactiver</button>
                    </form>
                </details>
            @else
                <p class="small muted">Obligatoire pour un super administrateur : elle ne peut pas être désactivée.</p>
            @endif
        </section>
    @else
        <section class="panel">
            <h2>1. Ajoutez le compte dans votre application</h2>
            <p class="muted">Dans Google Authenticator, Microsoft Authenticator, Authy ou 1Password : « Ajouter un compte » puis « Saisir une clé de configuration », type « basé sur l'heure ».</p>
            <div class="field"><label>Clé de configuration</label><div class="secret">{{ $secret }}</div></div>
            <div class="field"><label>Ou lien de configuration</label><div class="secret small" style="font-size:.78rem">{{ $uri }}</div></div>
        </section>
        <section class="panel">
            <h2>2. Confirmez avec un code</h2>
            <form method="POST" action="{{ route('console.twofactor.setup.confirm') }}" novalidate style="max-width:320px">
                @csrf
                <div class="field">
                    <label for="code">Code à 6 chiffres</label>
                    <input id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required>
                </div>
                <button class="btn">Activer</button>
            </form>
        </section>
    @endif
    </div>
@endsection
