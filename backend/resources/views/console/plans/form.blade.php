@extends('console.layout')
@php($isNew = ! $plan->exists)
@section('title', $isNew ? 'Nouveau forfait' : 'Modifier le forfait')
@section('content')
    <header class="page-head">
        <div><h1>{{ $isNew ? 'Nouveau forfait' : 'Modifier « '.$plan->name.' »' }}</h1>
            <p class="muted">Laissez une limite vide pour « illimité ».</p></div>
        <a class="btn btn-secondary" href="{{ route('console.plans.index') }}">← Forfaits</a>
    </header>

    <form method="POST" action="{{ $isNew ? route('console.plans.store') : route('console.plans.update', $plan) }}" novalidate style="max-width:760px">
        @csrf @if(! $isNew) @method('PUT') @endif
        <section class="panel">
            <h2>Offre</h2>
            <div class="row">
                <div class="field"><label for="name">Nom *</label><input id="name" name="name" value="{{ old('name', $plan->name) }}" required>@error('name')<p class="err">{{ $message }}</p>@enderror</div>
                @if($isNew)
                    <div class="field"><label for="slug">Identifiant *</label><input id="slug" name="slug" value="{{ old('slug') }}" autocapitalize="none" required>@error('slug')<p class="err">{{ $message }}</p>@enderror</div>
                @else
                    <div class="field"><label>Identifiant</label><input value="{{ $plan->slug }}" disabled></div>
                @endif
            </div>
            <div class="field"><label for="description">Description</label><input id="description" name="description" value="{{ old('description', $plan->description) }}">@error('description')<p class="err">{{ $message }}</p>@enderror</div>
            <div class="row">
                <div class="field"><label for="price">Prix par période *</label><input id="price" name="price" type="number" min="0" step="1" value="{{ old('price', (int) $plan->price) }}" required>@error('price')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="currency">Devise *</label><input id="currency" name="currency" maxlength="3" value="{{ old('currency', $plan->currency) }}" required>@error('currency')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="period_months">Période (mois) *</label><input id="period_months" name="period_months" type="number" min="1" max="24" value="{{ old('period_months', $plan->period_months) }}" required></div>
            </div>
            <div class="field">
                <label for="tiers">Paliers payants (optionnel)</label>
                <textarea id="tiers" name="tiers" rows="3" placeholder="10=5000&#10;25=9000">{{ old('tiers', collect($plan->tiers ?? [])->map(fn ($t) => $t['up_to'].'='.(int) $t['price'])->implode("\n")) }}</textarea>
                <p class="hint">Une ligne par palier : <code>effectif=prix</code> (ex. <code>10=5000</code> = jusqu'à 10 utilisateurs : 5 000 par période). Affichage indicatif.</p>
                @error('tiers')<p class="err">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="panel">
            <h2>Limites</h2>
            <div class="row">
                <div class="field"><label for="max_users">Utilisateurs</label><input id="max_users" name="max_users" type="number" min="1" value="{{ old('max_users', $plan->max_users) }}" placeholder="Illimité">@error('max_users')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="max_products">Produits</label><input id="max_products" name="max_products" type="number" min="1" value="{{ old('max_products', $plan->max_products) }}" placeholder="Illimité">@error('max_products')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="max_invoices_month">Factures par mois</label><input id="max_invoices_month" name="max_invoices_month" type="number" min="1" value="{{ old('max_invoices_month', $plan->max_invoices_month) }}" placeholder="Illimité">@error('max_invoices_month')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="max_branches">Points de vente (hors siège)</label><input id="max_branches" name="max_branches" type="number" min="1" value="{{ old('max_branches', $plan->max_branches) }}" placeholder="Illimité">@error('max_branches')<p class="err">{{ $message }}</p>@enderror</div>
            </div>
            <p class="hint">À la limite, les <strong>créations</strong> sont refusées ; les données existantes ne sont jamais touchées.</p>
        </section>

        <section class="panel">
            <h2>Options</h2>
            <label style="display:flex;gap:.5rem;align-items:center;margin-bottom:.6rem"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))> Proposé aux nouveaux abonnés</label>
            <label style="display:flex;gap:.5rem;align-items:center;margin-bottom:.6rem"><input type="checkbox" name="is_trial" value="1" @checked(old('is_trial', $plan->is_trial))> Forfait d'essai gratuit (attribué automatiquement à la création)</label>
            <div class="row">
                <div class="field"><label for="trial_days">Durée de l'essai (jours)</label><input id="trial_days" name="trial_days" type="number" min="1" max="365" value="{{ old('trial_days', $plan->trial_days) }}">@error('trial_days')<p class="err">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="sort">Ordre d'affichage</label><input id="sort" name="sort" type="number" min="0" value="{{ old('sort', $plan->sort ?? 0) }}"></div>
            </div>
        </section>
        <button class="btn">Enregistrer</button>
    </form>
@endsection
