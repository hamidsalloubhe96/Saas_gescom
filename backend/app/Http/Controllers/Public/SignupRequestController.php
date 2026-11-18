<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SignupRequest;
use App\Services\Turnstile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** « Demander un accès » : formulaire public. Protégé par limitation, champ piège et anti-robot optionnel. */
class SignupRequestController extends Controller
{
    public function store(Request $request, Turnstile $turnstile): JsonResponse
    {
        // Champ piège : invisible pour un humain, rempli par les robots. On répond « OK » sans rien enregistrer.
        if (filled($request->input('website'))) {
            return response()->json(['message' => 'Merci, nous vous recontactons très vite.'], 201);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', new \App\Rules\NotDisposableEmail],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['required', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'max:60'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($turnstile->verify($request), 422, 'La vérification anti-robot a échoué. Réessayez.');

        // Une même adresse ne peut pas inonder la file : une demande par heure.
        $recent = SignupRequest::where('email', strtolower($data['email']))->where('created_at', '>=', now()->subHour())->exists();
        if (! $recent) {
            SignupRequest::create($data + ['ip' => $request->ip()]);
        }

        return response()->json(['message' => 'Merci, nous vous recontactons très vite.'], 201);
    }
}
