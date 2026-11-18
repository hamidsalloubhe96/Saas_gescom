<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/** Recherche globale des utilisateurs de toutes les entreprises, et actions d'assistance. */
class UserDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,inactive'], 'tenant' => ['nullable', 'string', 'max:60']]);

        $query = TenantUser::query()->with('tenant')->orderBy('email');

        Search::contains($query, ['email', 'name'], (string) $request->query('q'));
        if ($t = $request->query('tenant')) {
            $query->where('tenant_id', $t);
        }
        if ($s = $request->query('status')) {
            $query->where('is_active', $s === 'active');
        }

        return view('console.users.index', ['users' => $query->paginate(25)->withQueryString()]);
    }

    /** Exécute $fn(User) dans l'espace de l'entreprise de cet utilisateur. */
    private function inTenant(TenantUser $entry, callable $fn): mixed
    {
        $tenant = Tenant::findOrFail($entry->tenant_id);

        return $tenant->run(function () use ($entry, $fn) {
            $user = User::find($entry->user_id);
            abort_unless($user, 404, 'Utilisateur introuvable dans cette entreprise.');

            return $fn($user);
        });
    }

    public function toggleActive(TenantUser $entry): RedirectResponse
    {
        $active = ! $entry->is_active;

        $this->inTenant($entry, function (User $u) use ($active) {
            $u->update(['is_active' => $active]); // l'observateur met l'annuaire à jour
            if (! $active) {
                $u->tokens()->delete(); // déconnecte immédiatement
            }
        });

        AuditLog::record($active ? 'user.activate' : 'user.deactivate',
            ($active ? 'Utilisateur réactivé : ' : 'Utilisateur désactivé : ').$entry->email, $entry->tenant_id, ['user' => $entry->user_id]);

        return back()->with('success', $active ? 'Utilisateur réactivé.' : 'Utilisateur désactivé et déconnecté.');
    }

    public function forceLogout(TenantUser $entry): RedirectResponse
    {
        $count = $this->inTenant($entry, fn (User $u) => $u->tokens()->delete());
        AuditLog::record('user.logout', "Sessions fermées pour {$entry->email}", $entry->tenant_id, ['user' => $entry->user_id, 'sessions' => $count]);

        return back()->with('success', "Utilisateur déconnecté ({$count} session(s) fermée(s)).");
    }

    public function resetPassword(TenantUser $entry): RedirectResponse
    {
        $temporary = Str::password(12, symbols: false);

        $this->inTenant($entry, function (User $u) use ($temporary) {
            $u->forceFill(['password' => $temporary, 'must_change_password' => true])->save();
            $u->tokens()->delete();
        });

        AuditLog::record('user.reset_password', "Mot de passe réinitialisé pour {$entry->email}", $entry->tenant_id, ['user' => $entry->user_id]);

        return back()->with('success', "Mot de passe provisoire généré pour {$entry->email}. Il devra être changé à la prochaine connexion.")
            ->with('temp_password', $temporary);
    }

    /**
     * Connexion d'assistance : jeton de 30 minutes, lecture seule par défaut. Le droit de modification est réservé au
     * super administrateur et exige un motif. Chaque ouverture est inscrite dans le journal.
     */
    public function impersonate(Request $request, TenantUser $entry): RedirectResponse
    {
        $admin = $request->user('admin');
        $write = $request->input('mode') === 'write';

        if ($write) {
            abort_unless($admin->can('users.impersonate.write'), 403, 'Seul un super administrateur peut se connecter avec droit de modification.');
            $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], ['reason.required' => 'Indiquez le motif de la connexion avec droit de modification.']);
        }

        $tenant = Tenant::findOrFail($entry->tenant_id);
        if (! $tenant->isUsable()) {
            return back()->withErrors(['impersonate' => 'Cette entreprise est suspendue ou bannie : connexion d\'assistance impossible.']);
        }
        if (! $entry->is_active) {
            return back()->withErrors(['impersonate' => 'Ce compte utilisateur est désactivé.']);
        }

        $minutes = (int) config('console.impersonation_minutes', 30);
        $plain = $this->inTenant($entry, fn (User $u) => $u->createToken('impersonation', $write ? ['*'] : ['read-only'], now()->addMinutes($minutes))->plainTextToken);

        // Code à usage unique, valable 60 s ; le jeton est chiffré dans le cache.
        $code = Str::random(48);
        Cache::put('impersonation:'.hash('sha256', $code), [
            'tenant' => $tenant->id, 'token' => Crypt::encryptString($plain), 'read_only' => ! $write,
        ], 60);

        AuditLog::record('user.impersonate',
            "Connexion d'assistance ({$minutes} min, ".($write ? 'modification' : 'lecture seule').") en tant que {$entry->email}",
            $tenant->id, ['user' => $entry->user_id, 'write' => $write, 'reason' => $request->input('reason')]);

        return redirect()->away($this->appUrl().'/impersonate?code='.$code);
    }

    /** Adresse de l'interface React : FRONTEND_URL en développement, sinon la même adresse que la console. */
    private function appUrl(): string
    {
        $configured = trim(explode(',', (string) config('app.frontend_url'))[0]);

        return rtrim($configured !== '' ? $configured : url('/'), '/');
    }
}
