<!doctype html>
<html lang="fr">
<body style="font-family:system-ui,Segoe UI,Arial,sans-serif;color:#1b1f2a;line-height:1.5">
<p>Bonjour {{ $name }},</p>
<p>L'espace <strong>{{ $tenant->name }}</strong> a été créé sur Salloubhe Gescom. Voici vos accès :</p>
<table cellpadding="6" style="border-collapse:collapse">
    <tr><td>Adresse</td><td><a href="{{ $loginUrl }}">{{ $loginUrl }}</a></td></tr>
    <tr><td>Identifiant de l'entreprise</td><td><strong>{{ $tenant->id }}</strong></td></tr>
    <tr><td>E-mail</td><td><strong>{{ $email }}</strong></td></tr>
    <tr><td>Mot de passe provisoire</td><td><strong style="font-family:monospace">{{ $temporaryPassword }}</strong></td></tr>
</table>
<p>Ce mot de passe est <strong>provisoire</strong> : il vous sera demandé d'en choisir un nouveau dès votre première connexion.</p>
<p>L'équipe Salloubhe Gescom</p>
</body>
</html>
