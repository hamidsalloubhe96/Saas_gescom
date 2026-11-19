<!doctype html>
<html lang="fr">
<body style="font-family:system-ui,Segoe UI,Arial,sans-serif;color:#1b1f2a;line-height:1.5">
<p>Bonjour,</p>
@if($kind === 'expiring')
<p>L'abonnement de <strong>{{ $company }}</strong> arrive à échéance le <strong>{{ $date }}</strong>. Pensez à régler votre abonnement depuis la page « Abonnement » pour éviter toute interruption.</p>
@else
<p>L'abonnement de <strong>{{ $company }}</strong> est arrivé à échéance le <strong>{{ $date }}</strong>. Vos données sont conservées : réglez votre abonnement depuis la page « Abonnement » pour retrouver l'accès complet.</p>
@endif
<p>L'équipe Salloubhe Gescom</p>
</body>
</html>
