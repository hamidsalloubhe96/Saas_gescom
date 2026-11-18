<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sert l'interface React (public/index.html) pour toute adresse inconnue de Laravel,
 * afin que /clients, /ventes/12… fonctionnent aussi à l'actualisation de la page.
 *
 * En production l'interface est copiée dans public/ par le Dockerfile : un seul service.
 * En développement elle est servie par Vite (port 5173) et ce fichier n'existe pas.
 */
class SpaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Une adresse d'API inconnue doit rester une vraie 404 (JSON), jamais une page HTML.
        if ($request->is('api/*')) {
            abort(404);
        }

        // Un fichier manquant (ex. /assets/index-ANCIEN.js après un déploiement) doit renvoyer 404 :
        // lui répondre par du HTML provoquerait une erreur « Unexpected token < » côté navigateur.
        if (preg_match('/\.[A-Za-z0-9]{1,8}$/', $request->path())) {
            abort(404);
        }

        $index = public_path('index.html');
        if (! is_file($index)) {
            abort(404, 'Interface non installée (en développement, utilisez le serveur Vite).');
        }

        return response()->file($index, ['Cache-Control' => 'no-cache']);
    }
}
