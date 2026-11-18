<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex, nofollow">
<title>Reçu {{ $r['number'] }}</title>
<style>
body{font-family:system-ui,Segoe UI,Arial,sans-serif;color:#1b1f2a;max-width:640px;margin:2rem auto;padding:0 1rem}
h1{margin:0 0 .25rem}table{width:100%;border-collapse:collapse;margin:1.5rem 0}td{padding:.55rem 0;border-bottom:1px solid #ddd}td:first-child{color:#555;width:40%}
.total{font-size:1.4rem;font-weight:700}.noprint button{padding:.5rem 1rem}@media print{.noprint{display:none}}
</style>
</head>
<body>
<h1>Reçu de paiement</h1>
<p>{{ $r['issuer'] }} · N° <strong>{{ $r['number'] }}</strong></p>
<table>
<tr><td>Entreprise</td><td>{{ $r['company'] }}</td></tr>
<tr><td>Forfait</td><td>{{ $r['plan'] ?? '—' }} · {{ $r['months'] }} période(s)</td></tr>
<tr><td>Mode de paiement</td><td>{{ $r['method'] }}@if($r['reference']) · réf. {{ $r['reference'] }}@endif</td></tr>
<tr><td>Date du paiement</td><td>{{ $r['paid_at'] ? \Illuminate\Support\Carbon::parse($r['paid_at'])->format('d/m/Y') : '—' }}</td></tr>
<tr><td>Validé le</td><td>{{ \Illuminate\Support\Carbon::parse($r['validated_at'])->format('d/m/Y') }}</td></tr>
<tr><td>Montant reçu</td><td class="total">{{ number_format($r['amount'], 0, ',', ' ') }} {{ $r['currency'] }}</td></tr>
</table>
<p class="noprint"><button onclick="window.print()">Imprimer</button></p>
</body>
</html>
