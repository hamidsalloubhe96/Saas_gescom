<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Connexion') · GestCom Console</title>
    @include('console._styles')
    @stack('head')
</head>
<body>
<main class="guest">
    <div class="guest-card">
        <div class="brand"><span class="brand-mark">G</span><span>GestCom Console</span></div>
        @include('console._flash')
        @yield('content')
    </div>
</main>
</body>
</html>
