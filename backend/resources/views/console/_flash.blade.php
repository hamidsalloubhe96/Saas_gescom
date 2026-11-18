@if(session('success'))<div class="alert alert-ok" role="status">{{ session('success') }}</div>@endif
@if(session('warning'))<div class="alert alert-warn" role="alert">{{ session('warning') }}</div>@endif
@if(session('temp_password'))
    <div class="panel" role="alert">
        <h2>Mot de passe provisoire (affiché une seule fois)</h2>
        <div class="secret">{{ session('temp_password') }}</div>
        <p class="muted small" style="margin-bottom:0">Copiez-le maintenant et transmettez-le par un canal sûr. Il devra être changé à la première connexion.</p>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-bad" role="alert">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif
