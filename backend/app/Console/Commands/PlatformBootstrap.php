<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PlatformBootstrap extends Command
{
    protected $signature = 'platform:bootstrap';

    protected $description = 'Crée le premier administrateur de la console et la première entreprise à partir des variables BOOTSTRAP_* (sans e-mail, idempotent)';

    public function handle(): int
    {
        $this->admin();
        $this->company();

        return self::SUCCESS;
    }

    private function admin(): void
    {
        $email = strtolower(trim((string) config('bootstrap.admin_email')));
        $password = (string) config('bootstrap.admin_password');
        if ($email === '' && $password === '') {
            return;
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $this->warn('Administrateur ignoré : BOOTSTRAP_ADMIN_EMAIL invalide ou BOOTSTRAP_ADMIN_PASSWORD de moins de 8 caractères.');

            return;
        }
        if (PlatformAdmin::where('email', $email)->exists()) {
            $this->line("Administrateur {$email} déjà présent : rien à faire.");

            return;
        }

        PlatformAdmin::create([
            'name' => config('bootstrap.admin_name') ?: strstr($email, '@', true),
            'email' => $email,
            'password' => $password,
            'role' => 'super_admin',
            'must_change_password' => false,
        ]);
        $this->info("Administrateur de la console créé : {$email}");
    }

    private function company(): void
    {
        $c = config('bootstrap');
        if (blank($c['company']) && blank($c['slug'])) {
            return;
        }
        $data = [
            'company' => trim((string) $c['company']),
            'slug' => strtolower(trim((string) $c['slug'])),
            'name' => trim((string) ($c['user_name'] ?: $c['company'])),
            'email' => strtolower(trim((string) $c['user_email'])),
        ];
        $password = (string) $c['user_password'];

        if (Tenant::whereKey($data['slug'])->exists()) {
            $this->line("Entreprise {$data['slug']} déjà présente : rien à faire.");

            return;
        }
        $v = Validator::make($data + ['password' => $password], [
            'company' => ['required', 'max:120'],
            'slug' => TenantProvisioner::slugRules(),
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
        ]);
        if ($v->fails()) {
            $this->warn('Entreprise ignorée : '.implode(' ', $v->errors()->all()));

            return;
        }

        try {
            app(TenantProvisioner::class)->provision($data, $password);
            $this->info("Entreprise créée : {$data['company']} (identifiant {$data['slug']}), administrateur {$data['email']}");
        } catch (Throwable $e) {
            report($e);
            $this->error('Création de l\'entreprise impossible : '.$e->getMessage());
        }
    }
}
