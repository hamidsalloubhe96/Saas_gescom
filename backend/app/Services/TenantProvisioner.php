<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Création d'une entreprise : schéma PostgreSQL + migrations (événement TenantCreated) + premier administrateur.
 * Utilisé par l'inscription publique ET par la console : une seule règle de création.
 */
class TenantProvisioner
{
    public const RESERVED_SLUGS = ['www', 'api', 'admin', 'app', 'console', 'central', 'public', 'pg_catalog', 'information_schema'];

    /** Règles de validation de l'identifiant (slug) de l'entreprise. */
    public static function slugRules(): array
    {
        return [
            'required',
            'regex:/^[a-z][a-z0-9_]{2,29}$/',
            Rule::notIn(self::RESERVED_SLUGS),
            Rule::unique('tenants', 'id'),
        ];
    }

    /**
     * @param  array{company: string, slug: string, name: string, email: string, country?: ?string, city?: ?string, business_type?: ?string}  $data
     * @return array{tenant: Tenant, user: User, token: ?string}
     */
    public function provision(array $data, string $password, bool $withToken = false, bool $mustChangePassword = false): array
    {
        // Schéma + migrations tenant : TenantCreated -> CreateDatabase, MigrateDatabase.
        $tenant = Tenant::create([
            'id' => $data['slug'],
            'name' => $data['company'],
            'owner_email' => $data['email'],
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'status' => Tenant::STATUS_ACTIVE,
            'business_type' => $data['business_type'] ?? 'general',
        ]);

        app(SubscriptionService::class)->startTrial($tenant);

        try {
            [$user, $token] = $tenant->run(function () use ($data, $password, $withToken, $mustChangePassword) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $password, // haché par le cast "hashed"
                    'role' => 'admin',
                    'must_change_password' => $mustChangePassword,
                ]);

                return [$user, $withToken ? $user->createToken('spa')->plainTextToken : null];
            });
        } catch (Throwable $e) {
            $tenant->delete(); // supprime aussi le schéma
            throw $e;
        }

        return ['tenant' => $tenant, 'user' => $user, 'token' => $token];
    }
}
