<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\LoginAttempts;
use App\Services\Turnstile;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, Turnstile $turnstile, LoginAttempts $attempts): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $turnstile->verify($request)) {
            throw ValidationException::withMessages(['email' => ['Vérification anti-robot échouée : réessayez.']]);
        }

        // Blocage progressif par compte : après 5 échecs, une pause qui s'allonge (15 min au plus).
        $key = LoginAttempts::key('t:'.tenant('id'), $credentials['email']);
        if ($wait = $attempts->waitSeconds($key)) {
            throw new HttpResponseException(response()->json(['message' => LoginAttempts::message($wait), 'errors' => ['email' => [LoginAttempts::message($wait)]]], 429));
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $attempts->fail($key);

            throw ValidationException::withMessages(['email' => ['Identifiants invalides.']]);
        }
        $attempts->clear($key);
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => ['Ce compte est désactivé. Contactez votre administrateur.']]);
        }

        // Alimente « dernière activité » (entreprise) et « dernière connexion » (annuaire) pour la console.
        tenant()->forceFill(['last_activity_at' => now()])->saveQuietly();
        TenantUser::where('tenant_id', tenant('id'))->where('user_id', $user->id)->update(['last_login_at' => now()]);

        return response()->json([
            'token' => $user->createToken('spa')->plainTextToken,
            'user' => $user->present(),
            'tenant' => tenant('id'),
            'company' => tenant('name'),
        ] + \App\Http\Controllers\BranchController::payloadFor($user) + app(\App\Services\TenantContext::class)->forClient(tenant()));
    }

    public function me(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        return response()->json([
            'user' => $request->user()->present(),
            'tenant' => tenant('id'),
            'company' => tenant('name'),
            ...\App\Http\Controllers\BranchController::payloadFor($request->user()),
            // Connexion d'assistance ouverte par la console : l'interface affiche un bandeau permanent.
            'impersonation' => $token && $token->name === 'impersonation'
                ? ['read_only' => ! in_array('*', $token->abilities ?? [], true), 'expires_at' => $token->expires_at?->toIso8601String()]
                : null,
        ] + app(\App\Services\TenantContext::class)->forClient(tenant()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    /** Mon compte : nom et e-mail. Changer l'e-mail exige le mot de passe actuel. */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->currentAccessToken()?->name === 'impersonation', 403, "Impossible depuis une connexion d'assistance.");

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => [\Illuminate\Validation\Rule::requiredIf(fn () => strtolower($request->input('email', '')) !== strtolower($user->email)), 'nullable', 'string'],
        ], ['email.unique' => 'Cette adresse e-mail est déjà utilisée.', 'current_password.required' => "Saisissez votre mot de passe actuel pour changer d'e-mail."]);

        if (strtolower($data['email']) !== strtolower($user->email) && ! Hash::check($data['current_password'] ?? '', $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Mot de passe actuel incorrect.']]);
        }

        $user->update(['name' => $data['name'], 'email' => $data['email']]);

        return response()->json(['user' => $user->fresh()->present()]);
    }

    /** Changement de son propre mot de passe (obligatoire après un mot de passe provisoire). */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($request->user()->currentAccessToken()?->name === 'impersonation', 403, 'Impossible depuis une connexion d\'assistance.');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Mot de passe actuel incorrect.']]);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['Le nouveau mot de passe doit être différent de l\'actuel.']]);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        // Les autres appareils sont déconnectés ; la session courante est conservée.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['user' => $user->fresh()->present()]);
    }
}
