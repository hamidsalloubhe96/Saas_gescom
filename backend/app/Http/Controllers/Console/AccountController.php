<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/** Mon compte : mot de passe et double authentification (TOTP, Google Authenticator, Authy, 1Password…). */
class AccountController extends Controller
{
    public function showPassword()
    {
        return view('console.password');
    }

    /** Changement de l'e-mail de connexion : exige le mot de passe actuel. */
    public function updateEmail(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190', \Illuminate\Validation\Rule::unique('platform_admins', 'email')->ignore($admin->id)],
            'current_password' => ['required', 'string'],
        ], ['email.unique' => 'Cette adresse e-mail est déjà utilisée.']);

        if (! Hash::check($data['current_password'], $admin->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe actuel incorrect.']);
        }
        $old = $admin->email;
        $admin->forceFill(['email' => $data['email']])->save();
        AuditLog::record('auth.email_changed', "E-mail modifié : {$old} -> {$data['email']}", null, [], $admin);

        return back()->with('success', 'Adresse e-mail modifiée.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $admin->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe actuel incorrect.']);
        }
        if (Hash::check($data['password'], $admin->password)) {
            throw ValidationException::withMessages(['password' => 'Le nouveau mot de passe doit être différent de l\'actuel.']);
        }

        $admin->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        AuditLog::record('auth.password_changed', 'Mot de passe modifié', null, [], $admin);

        return redirect()->route('console.dashboard')->with('success', 'Mot de passe modifié.');
    }

    public function showTwoFactorSetup(Request $request, Google2FA $google2fa)
    {
        $admin = $request->user('admin');

        if ($admin->two_factor_confirmed_at) {
            return view('console.twofactor-setup', ['enabled' => true, 'secret' => null, 'uri' => null]);
        }

        // Le secret reste en session tant que l'admin n'a pas prouvé qu'il sait générer un code.
        $secret = $request->session()->get('console.2fa_setup') ?? tap($google2fa->generateSecretKey(), fn ($s) => $request->session()->put('console.2fa_setup', $s));
        $uri = $google2fa->getQRCodeUrl('Salloubhe Gescom Console', $admin->email, $secret);

        return view('console.twofactor-setup', ['enabled' => false, 'secret' => $secret, 'uri' => $uri]);
    }

    public function confirmTwoFactor(Request $request, Google2FA $google2fa): RedirectResponse
    {
        $admin = $request->user('admin');
        $secret = $request->session()->get('console.2fa_setup');
        $request->validate(['code' => ['required', 'digits:6']]);

        if (! $secret || ! $google2fa->verifyKey($secret, $request->string('code')->toString(), 1)) {
            throw ValidationException::withMessages(['code' => 'Code invalide. Vérifiez l\'heure de votre téléphone et réessayez.']);
        }

        $admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $request->session()->forget('console.2fa_setup');
        AuditLog::record('auth.2fa_enabled', 'Double authentification activée', null, [], $admin);

        return redirect()->route('console.dashboard')->with('success', 'Double authentification activée.');
    }

    /** Un super administrateur ne peut pas la désactiver : elle lui est imposée. */
    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_if($admin->isSuper(), 403, 'La double authentification est obligatoire pour un super administrateur.');

        $request->validate(['current_password' => ['required', 'string']]);
        if (! Hash::check($request->input('current_password'), $admin->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe actuel incorrect.']);
        }

        $admin->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLog::record('auth.2fa_disabled', 'Double authentification désactivée', null, [], $admin);

        return redirect()->route('console.twofactor.setup')->with('success', 'Double authentification désactivée.');
    }
}
