<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Échange le code à usage unique (60 s) donné par la console contre le jeton d'assistance.
 * Route centrale, sans entreprise identifiée : le code contient déjà tout.
 */
class ImpersonationController extends Controller
{
    public function exchange(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:48']]);

        // pull = lecture ET suppression : le code ne sert qu'une fois.
        $payload = Cache::pull('impersonation:'.hash('sha256', $data['code']));
        abort_unless($payload, 410, 'Lien expiré ou déjà utilisé. Relancez la connexion depuis la console.');

        $tenant = Tenant::find($payload['tenant']);
        abort_unless($tenant && $tenant->isUsable(), 403, 'Cette entreprise n\'est pas active.');

        return response()->json([
            'token' => \Illuminate\Support\Facades\Crypt::decryptString($payload['token']),
            'tenant' => $tenant->id,
            'company' => $tenant->name,
            'read_only' => $payload['read_only'],
        ]);
    }
}
