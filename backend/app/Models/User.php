<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'must_change_password', 'branch_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /** Permissions accordées par le rôle (voir config/permissions.php). Rôle inconnu = aucun droit. */
    public function permissions(): array
    {
        $role = config("permissions.roles.{$this->role}");
        if (! $role) {
            return [];
        }

        return in_array('*', $role['permissions'], true)
            ? array_keys(config('permissions.permissions'))
            : $role['permissions'];
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** Représentation envoyée au frontend : utilisateur + libellé du rôle + permissions. */
    public function present(): array
    {
        return $this->toArray() + [
            'role_label' => config("permissions.roles.{$this->role}.label", $this->role),
            'permissions' => $this->permissions(),
        ];
    }
}
