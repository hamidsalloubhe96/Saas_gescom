<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use App\Services\PasswordResetCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Mot de passe oublié de la console : code à 6 chiffres par e-mail. La double authentification reste exigée ensuite. */
class PasswordResetController extends Controller
{
    private const SCOPE = 'console';

    public function showForgot()
    {
        return view('console.forgot');
    }

    public function sendCode(Request $request, PasswordResetCode $codes): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $admin = PlatformAdmin::where('email', $data['email'])->first();

        if (! $admin) {
            throw ValidationException::withMessages(['email' => 'Aucun compte avec cet e-mail.']);
        }
        if (! $admin->is_active) {
            throw ValidationException::withMessages(['email' => 'Ce compte est désactivé.']);
        }
        if ($wait = $codes->waitSeconds(self::SCOPE, $admin->email)) {
            throw ValidationException::withMessages(['email' => "Un code vient d'être envoyé. Patientez {$wait} s."]);
        }

        $codes->send(self::SCOPE, $admin->email, 'console Salloubhe Gescom');
        AuditLog::record('auth.reset_code_sent', "Code de réinitialisation envoyé à {$admin->email}", null, [], $admin);
        $request->session()->put('console.reset_email', $admin->email);

        return redirect()->route('console.reset')->with('success', 'Un code à 6 chiffres vient d\'être envoyé par e-mail (valable '.PasswordResetCode::TTL_MINUTES.' minutes).');
    }

    public function showReset(Request $request)
    {
        abort_unless($request->session()->has('console.reset_email'), 404);

        return view('console.reset', ['email' => $request->session()->get('console.reset_email')]);
    }

    public function reset(Request $request, PasswordResetCode $codes): RedirectResponse
    {
        $email = $request->session()->get('console.reset_email');
        if (! $email) {
            return redirect()->route('console.forgot');
        }
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $admin = PlatformAdmin::where('email', $email)->first();
        if (! $admin || ! $admin->is_active || ! $codes->consume(self::SCOPE, $admin->email, $data['code'])) {
            AuditLog::record('auth.reset_failed', "Code de réinitialisation invalide pour {$email}", null, [], null);

            throw ValidationException::withMessages(['code' => 'Code invalide ou expiré. Demandez-en un nouveau.']);
        }

        // Le changement de hachage ferme aussi les sessions ouvertes (AuthenticateSession).
        $admin->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        AuditLog::record('auth.password_reset', 'Mot de passe réinitialisé par code e-mail', null, [], $admin);
        $request->session()->forget('console.reset_email');

        return redirect()->route('console.login')->with('success', 'Mot de passe modifié. Connectez-vous.');
    }
}
