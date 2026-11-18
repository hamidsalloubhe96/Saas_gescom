<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateConsoleAdmin extends Command
{
    protected $signature = 'console:create-admin
        {email : Adresse e-mail de connexion}
        {--name= : Nom affiché}
        {--role=super_admin : super_admin, admin ou support}
        {--password= : Mot de passe (sinon un mot de passe provisoire est généré et affiché une fois)}';

    protected $description = 'Crée un compte de la console de la plateforme (premier super administrateur, par exemple)';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $role = $this->option('role');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }
        if (! array_key_exists($role, PlatformAdmin::ROLES)) {
            $this->error('Rôle inconnu. Valeurs possibles : '.implode(', ', array_keys(PlatformAdmin::ROLES)));

            return self::FAILURE;
        }
        if (PlatformAdmin::where('email', $email)->exists()) {
            $this->error("Un compte existe déjà pour {$email}.");

            return self::FAILURE;
        }

        $generated = ! $this->option('password');
        $password = $this->option('password') ?: Str::password(14, symbols: false);

        PlatformAdmin::create([
            'name' => $this->option('name') ?: Str::before($email, '@'),
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'must_change_password' => $generated, // un mot de passe généré doit être changé à la première connexion
        ]);

        $this->info("Compte créé : {$email} ({$role})");
        if ($generated) {
            $this->warn("Mot de passe provisoire (à changer à la première connexion) : {$password}");
        }

        return self::SUCCESS;
    }
}
