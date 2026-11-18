<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Membre de l'équipe de la plateforme (console). Jamais mélangé avec App\Models\User (utilisateurs d'entreprises).
 */
class PlatformAdmin extends Authenticatable
{
    public const ROLE_SUPER = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPPORT = 'support';

    public const ROLES = [
        self::ROLE_SUPER => 'Super administrateur',
        self::ROLE_ADMIN => 'Administrateur',
        self::ROLE_SUPPORT => 'Support',
    ];

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'must_change_password', 'last_login_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function isSuper(): bool
    {
        return $this->role === self::ROLE_SUPER;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /** Autorisation par rôle : voir config/console.php. */
    public function can($abilities, $arguments = []): bool
    {
        if (! is_string($abilities)) {
            return parent::can($abilities, $arguments);
        }

        $allowed = config("console.roles.{$this->role}", []);

        return in_array('*', $allowed, true) || in_array($abilities, $allowed, true);
    }
}
