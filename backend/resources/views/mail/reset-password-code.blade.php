<!doctype html>
<html lang="fr">
<body style="font-family:system-ui,Segoe UI,Arial,sans-serif;color:#1b1f2a;line-height:1.5">
<p>Bonjour,</p>
<p>Voici votre code pour réinitialiser votre mot de passe ({{ $audience }}) :</p>
<p style="font-size:2rem;font-weight:700;letter-spacing:.3rem;font-family:monospace">{{ $code }}</p>
<p>Il est valable {{ $minutes }} minutes et ne fonctionne qu'une fois. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.</p>
<p>L'équipe GestCom</p>
</body>
</html>
