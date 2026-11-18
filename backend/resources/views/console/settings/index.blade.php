@extends('console.layout')
@section('title', 'Paramètres')
@section('content')
    <header class="page-head">
        <div><h1>Paramètres de la plateforme</h1><p class="muted">Réglages communs à toutes les entreprises.</p></div>
    </header>

    <form method="POST" action="{{ route('console.settings.update') }}" novalidate style="max-width:760px">
        @csrf @method('PUT')
        <section class="panel">
            <h2>Abonnements</h2>
            <div class="row">
                <div class="field"><label for="trial_days">Durée de l'essai (jours)</label><input id="trial_days" name="trial_days" type="number" min="1" max="365" value="{{ old('trial_days', $settings['trial_days']) }}">@error('trial_days')<p class="err">{{ $message }}</p>@enderror<p class="hint">S'applique aux nouvelles entreprises.</p></div>
                <div class="field"><label for="grace_days">Jours de grâce après l'échéance</label><input id="grace_days" name="grace_days" type="number" min="0" max="90" value="{{ old('grace_days', $settings['grace_days']) }}">@error('grace_days')<p class="err">{{ $message }}</p>@enderror<p class="hint">Après ce délai sans paiement, l'accès est suspendu.</p></div>
                <div class="field"><label for="currency">Devise</label><input id="currency" name="currency" maxlength="3" value="{{ old('currency', $settings['currency']) }}">@error('currency')<p class="err">{{ $message }}</p>@enderror</div>
            </div>
            <div class="field"><label for="payment_instructions">Instructions de paiement (affichées aux entreprises)</label>
                <textarea id="payment_instructions" name="payment_instructions" rows="3">{{ old('payment_instructions', $settings['payment_instructions']) }}</textarea>
                <p class="hint">Ex. numéro de compte, numéro mobile money, délai de validation.</p></div>
        </section>

        <section class="panel">
            <h2>Inscriptions et nettoyage</h2>
            <label style="display:flex;gap:.5rem;align-items:center;margin-bottom:.8rem"><input type="checkbox" name="signup_open" value="1" @checked(old('signup_open', $settings['signup_open']))> Inscriptions ouvertes (création libre d'une entreprise depuis le site)</label>
            <label style="display:flex;gap:.5rem;align-items:center;margin-bottom:.8rem"><input type="checkbox" name="email_verification" value="1" @checked(old('email_verification', $settings['email_verification'])) > Vérifier l'e-mail à l'inscription (code reçu par e-mail avant la création de l'entreprise)</label>
            <div class="row">
                <div class="field"><label for="deletion_grace_days">Délai avant suppression définitive (jours)</label><input id="deletion_grace_days" name="deletion_grace_days" type="number" min="7" max="365" value="{{ old('deletion_grace_days', $settings['deletion_grace_days']) }}">@error('deletion_grace_days')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="abandoned_days">Entreprise « abandonnée » après (jours sans connexion)</label><input id="abandoned_days" name="abandoned_days" type="number" min="30" max="1095" value="{{ old('abandoned_days', $settings['abandoned_days']) }}">@error('abandoned_days')<p class="err">{{ $message }}</p>@enderror</div>
            </div>
        </section>
        <section class="panel">
            <h2>Coordonnées publiques (page vitrine)</h2>
            <div class="row">
                <div class="field"><label for="contact_email">E-mail de contact</label><input id="contact_email" name="contact_email" type="email" maxlength="190" value="{{ old('contact_email', $settings['contact_email']) }}">@error('contact_email')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="contact_phone">Téléphones (séparés par « / »)</label><input id="contact_phone" name="contact_phone" maxlength="80" value="{{ old('contact_phone', $settings['contact_phone']) }}" placeholder="+221 77 000 00 00">@error('contact_phone')<p class="err">{{ $message }}</p>@enderror</div>
            </div>
        </section>
        <button class="btn">Enregistrer</button>
    </form>

    <section class="panel" style="max-width:760px;margin-top:1rem">
        <h2>Mode maintenance @if($settings['maintenance'])<span class="pill pill-warn">Activé</span>@endif</h2>
        <p class="muted">Toute l'API des entreprises répond « en maintenance ». La console reste accessible. À réserver aux mises à jour importantes.</p>
        <form method="POST" action="{{ route('console.settings.maintenance') }}">@csrf
            <div class="field"><label for="m">Message affiché aux entreprises (optionnel)</label><input id="m" name="message" maxlength="300" value="{{ $settings['maintenance_message'] }}" placeholder="Mise à jour en cours, retour dans quelques minutes."></div>
            @if($settings['maintenance'])
                <input type="hidden" name="on" value="0"><button class="btn">Désactiver la maintenance</button>
            @else
                <input type="hidden" name="on" value="1"><button class="btn btn-danger" onclick="return confirm('Mettre toute la plateforme en maintenance ?')">Activer la maintenance</button>
            @endif
        </form>
    </section>
@endsection
