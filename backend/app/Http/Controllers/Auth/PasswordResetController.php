<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Mot de passe oublié côté entreprise : code à 6 chiffres par e-mail, puis nouveau mot de passe. */
class PasswordResetController extends Controller
{
    private function scope(): string
    {
        return 't:'.tenant('id');
    }

    public function forgot(Request $request, PasswordResetCode $codes): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => ['Aucun compte avec cet e-mail dans cette entreprise.']]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => ['Ce compte est désactivé. Contactez votre administrateur.']]);
        }
        if ($wait = $codes->waitSeconds($this->scope(), $user->email)) {
            throw ValidationException::withMessages(['email' => ["Un code vient d'être envoyé. Patientez {$wait} s avant d'en demander un autre."]]);
        }

        $codes->send($this->scope(), $user->email, tenant('name'));

        return response()->json(['message' => 'Un code à 6 chiffres vient d\'être envoyé par e-mail. Il est valable '.PasswordResetCode::TTL_MINUTES.' minutes.']);
    }

    public function reset(Request $request, PasswordResetCode $codes): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::where('email', $data['email'])->first();
        // Même message pour un compte inconnu, désactivé ou un mauvais code : on ne consomme le code que s'il est valide.
        if (! $user || ! $user->is_active || ! $codes->consume($this->scope(), $user->email, $data['code'])) {
            throw ValidationException::withMessages(['code' => ['Code invalide ou expiré. Demandez-en un nouveau.']]);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        $user->tokens()->delete(); // tous les appareils sont déconnectés

        return response()->json(['message' => 'Mot de passe modifié. Vous pouvez vous connecter.']);
    }
}
