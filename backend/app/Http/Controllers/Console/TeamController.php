<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Équipe de la console (super administrateur uniquement). */
class TeamController extends Controller
{
    public function index()
    {
        return view('console.team.index', [
            'admins' => PlatformAdmin::orderBy('name')->get(),
            'abilities' => config('console.abilities'),
            'roles' => config('console.roles'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:platform_admins,email'],
            'role' => ['required', Rule::in(array_keys(PlatformAdmin::ROLES))],
        ]);

        $temporary = Str::password(14, symbols: false);
        $admin = PlatformAdmin::create($data + ['password' => $temporary, 'must_change_password' => true]);

        AuditLog::record('team.create', "Compte console créé : {$admin->email} ({$admin->roleLabel()})", null, ['admin' => $admin->id]);

        return back()->with('success', "Compte créé pour {$admin->email}. Transmettez-lui son mot de passe provisoire.")->with('temp_password', $temporary);
    }

    public function update(Request $request, PlatformAdmin $admin): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(PlatformAdmin::ROLES))],
            'is_active' => ['required', 'boolean'],
        ]);

        $me = $request->user('admin');
        $losesAccess = $data['role'] !== $admin->role || ! $data['is_active'];

        if ($admin->id === $me->id && $losesAccess) {
            return back()->withErrors(['team' => 'Vous ne pouvez ni changer votre propre rôle ni désactiver votre propre compte.']);
        }
        if ($this->wouldRemoveLastSuper($admin, $data)) {
            return back()->withErrors(['team' => 'Il doit rester au moins un super administrateur actif.']);
        }

        $admin->update($data);
        AuditLog::record('team.update', "Compte {$admin->email} : rôle {$admin->roleLabel()}, ".($admin->is_active ? 'actif' : 'désactivé'), null, ['admin' => $admin->id]);

        return back()->with('success', 'Compte mis à jour.');
    }

    public function resetPassword(Request $request, PlatformAdmin $admin): RedirectResponse
    {
        abort_if($admin->id === $request->user('admin')->id, 403, 'Utilisez « Mon compte » pour changer votre propre mot de passe.');

        $temporary = Str::password(14, symbols: false);
        $admin->forceFill(['password' => $temporary, 'must_change_password' => true])->save();
        AuditLog::record('team.reset_password', "Mot de passe réinitialisé pour {$admin->email}", null, ['admin' => $admin->id]);

        return back()->with('success', "Mot de passe provisoire généré pour {$admin->email}.")->with('temp_password', $temporary);
    }

    private function wouldRemoveLastSuper(PlatformAdmin $admin, array $new): bool
    {
        $wasActiveSuper = $admin->role === PlatformAdmin::ROLE_SUPER && $admin->is_active;
        $staysActiveSuper = $new['role'] === PlatformAdmin::ROLE_SUPER && $new['is_active'];

        return $wasActiveSuper && ! $staysActiveSuper
            && PlatformAdmin::where('role', PlatformAdmin::ROLE_SUPER)->where('is_active', true)->where('id', '!=', $admin->id)->doesntExist();
    }
}
