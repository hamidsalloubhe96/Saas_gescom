<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Rules\NotDisposableEmail;
use App\Services\EmailVerification;
use App\Services\TenantProvisioner;
use App\Services\Turnstile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Inscription libre d'une entreprise. Protégée par : interrupteur d'ouverture (console), anti-robot optionnel,
 * refus des e-mails jetables et, si activée dans la console, vérification de l'adresse par un code.
 */
class RegisterTenantController extends Controller
{
    private function assertOpen(): void
    {
        abort_unless(PlatformSetting::bool('signup_open', true), 403,
            'Les inscriptions sont fermées pour le moment. Demandez un accès et nous vous recontactons.');
    }

    /** Étape 1 (si la vérification est activée) : envoie le code par e-mail. */
    public function sendCode(Request $request, EmailVerification $verification, Turnstile $turnstile): JsonResponse
    {
        $this->assertOpen();
        $data = $request->validate(['email' => ['required', 'email', 'max:190', new NotDisposableEmail]]);
        abort_unless($turnstile->verify($request), 422, 'La vérification anti-robot a échoué. Réessayez.');

        try {
            $verification->send($data['email']);
        } catch (\Throwable $e) {
            report($e);
            abort(503, "L'envoi de l'e-mail de vérification est momentanément indisponible. Réessayez plus tard ou contactez l'assistance.");
        }

        return response()->json(['message' => 'Un code de vérification vient d\'être envoyé à '.$data['email'].'.']);
    }

    public function __invoke(Request $request, TenantProvisioner $provisioner, EmailVerification $verification, Turnstile $turnstile): JsonResponse
    {
        $this->assertOpen();

        $data = $request->validate([
            'company' => ['required', 'string', 'max:120'],
            'slug' => TenantProvisioner::slugRules(),
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', new NotDisposableEmail],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'code' => ['nullable', 'string', 'max:10'],
        ]);

        // Sans code (vérification désactivée), l'anti-robot protège l'envoi direct ; avec code, il a déjà été contrôlé à l'étape 1.
        if (PlatformSetting::bool('email_verification', true)) {
            if (! $verification->check($data['email'], (string) ($data['code'] ?? ''))) {
                throw ValidationException::withMessages(['code' => ['Code invalide ou expiré. Demandez-en un nouveau.']]);
            }
        } else {
            abort_unless($turnstile->verify($request), 422, 'La vérification anti-robot a échoué. Réessayez.');
        }

        $result = $provisioner->provision($data, $data['password'], withToken: true);

        return response()->json([
            'tenant' => $result['tenant']->id,
            'company' => $result['tenant']->name,
            'token' => $result['token'],
        ], 201);
    }
}
