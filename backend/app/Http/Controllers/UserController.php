<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** Catalogue des rôles (libellés, description, permissions) pour les écrans d'administration. */
    public function roles(): JsonResponse
    {
        $all = config('permissions.permissions');

        $roles = collect(config('permissions.roles'))->map(fn ($r, $key) => [
            'key' => $key,
            'label' => $r['label'],
            'description' => $r['description'],
            'permissions' => in_array('*', $r['permissions'], true) ? array_keys($all) : $r['permissions'],
        ])->values();

        return response()->json(['roles' => $roles, 'permissions' => $all]);
    }

    public function index(): JsonResponse
    {
        return response()->json(User::orderBy('name')->get()->map->present()->values());
    }

    public function store(Request $request): JsonResponse
    {
        app(\App\Services\PlanLimits::class)->ensureCanAdd('users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'role' => ['required', Rule::in(array_keys(config('permissions.roles')))],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $data['branch_id'] = $data['role'] === 'admin' ? null : ($data['branch_id'] ?? null);

        return response()->json(User::create($data)->refresh()->present(), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'role' => ['required', Rule::in(array_keys(config('permissions.roles')))],
            'is_active' => ['required', 'boolean'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $data['branch_id'] = $data['role'] === 'admin' ? null : ($data['branch_id'] ?? null);

        $losesAccess = $data['role'] !== $user->role || ! $data['is_active'];

        // Garde-fous : on ne peut pas se couper l'accès soi-même, ni retirer le dernier administrateur.
        if ($user->id === $request->user()->id && $losesAccess) {
            abort(422, 'Vous ne pouvez ni changer votre propre rôle ni désactiver votre propre compte.');
        }
        $wasActiveAdmin = $user->role === 'admin' && $user->is_active;
        $staysActiveAdmin = $data['role'] === 'admin' && $data['is_active'];
        if ($wasActiveAdmin && ! $staysActiveAdmin
            && User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $id)->doesntExist()) {
            abort(422, 'Il doit rester au moins un administrateur actif.');
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        // Compte désactivé ou mot de passe changé : toutes ses sessions sont fermées.
        if (! $data['is_active'] || isset($data['password'])) {
            $user->tokens()->delete();
        }

        return response()->json($user->refresh()->present());
    }
}
