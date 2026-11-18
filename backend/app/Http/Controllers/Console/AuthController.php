<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    private const MAX_2FA_ATTEMPTS = 5;
    private const PENDING_TTL_SECONDS = 300;

    public function showLogin()
    {
        return view('console.login', ['turnstileKey' => config('services.turnstile.site_key')]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $this->verifyTurnstile($request);

        $admin = PlatformAdmin::where('email', $data['email'])->first();

        // Un calcul de hachage est toujours effectué, pour ne pas révéler par le temps de réponse si l'e-mail existe.
        $valid = Hash::check($data['password'], $admin?->password ?? Hash::make(Str::random(32)));

        if (! $admin || ! $valid || ! $admin->is_active) {
            AuditLog::record('auth.login_failed', "Échec de connexion console pour {$data['email']}", null, ['email' => $data['email']], null);

            throw ValidationException::withMessages(['email' => 'Identifiants invalides.']);
        }

        if ($admin->two_factor_confirmed_at) {
            $request->session()->put('console.2fa', ['id' => $admin->id, 'at' => time(), 'attempts' => 0]);

            return redirect()->route('console.twofactor');
        }

        return $this->completeLogin($request, $admin);
    }

    public function showTwoFactor(Request $request)
    {
        abort_unless($this->pending($request), 404);

        return view('console.twofactor-challenge');
    }

    public function verifyTwoFactor(Request $request, Google2FA $google2fa): RedirectResponse
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('console.login')->withErrors(['email' => 'La vérification a expiré, reconnectez-vous.']);
        }

        $request->validate(['code' => ['required', 'digits:6']]);
        $admin = PlatformAdmin::find($pending['id']);

        if (! $admin || ! $admin->is_active || ! $google2fa->verifyKey($admin->two_factor_secret, $request->string('code')->toString(), 1)) {
            $pending['attempts']++;
            if ($pending['attempts'] >= self::MAX_2FA_ATTEMPTS) {
                $request->session()->forget('console.2fa');
                AuditLog::record('auth.2fa_locked', "Trop d'essais de code de double authentification pour {$admin?->email}", null, [], null);

                return redirect()->route('console.login')->withErrors(['email' => 'Trop d\'essais. Reconnectez-vous.']);
            }
            $request->session()->put('console.2fa', $pending);
            AuditLog::record('auth.2fa_failed', "Code de double authentification invalide pour {$admin?->email}", null, [], null);

            throw ValidationException::withMessages(['code' => 'Code invalide.']);
        }

        $request->session()->forget('console.2fa');

        return $this->completeLogin($request, $admin);
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($admin = auth('admin')->user()) {
            AuditLog::record('auth.logout', 'Déconnexion de la console', null, [], $admin);
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('console.login');
    }

    private function completeLogin(Request $request, PlatformAdmin $admin): RedirectResponse
    {
        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        $admin->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('auth.login', 'Connexion à la console', null, [], $admin);

        return redirect()->intended(route('console.dashboard'));
    }

    /** Étape de double authentification en attente (expire au bout de 5 minutes). */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get('console.2fa');

        return $pending && (time() - $pending['at']) <= self::PENDING_TTL_SECONDS ? $pending : null;
    }

    /** Contrôle anti-robot Cloudflare Turnstile, actif seulement si les clés sont configurées. */
    private function verifyTurnstile(Request $request): void
    {
        $secret = config('services.turnstile.secret');
        if (! $secret) {
            return;
        }

        $ok = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret,
            'response' => $request->input('cf-turnstile-response'),
            'remoteip' => $request->ip(),
        ])->json('success', false);

        if (! $ok) {
            throw ValidationException::withMessages(['email' => 'La vérification anti-robot a échoué. Réessayez.']);
        }
    }
}
