<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Console de la plateforme : connexion, rôles, suspension d'une entreprise, journal d'audit.
 * Base SQLite en mémoire (phpunit.xml) : ne touche jamais à la base de développement.
 */
class ConsoleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin', array $extra = []): PlatformAdmin
    {
        $admin = PlatformAdmin::create($extra + [
            'name' => ucfirst($role), 'email' => "$role@test.local", 'password' => 'Password-12345', 'role' => $role,
            'is_active' => true,
        ]);

        // 2FA déjà configurée : le test porte sur autre chose. Ces champs ne sont (volontairement) pas
        // assignables en masse, d'où forceFill.
        $admin->forceFill(array_key_exists('two_factor_secret', $extra)
            ? [] : ['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $admin->fresh();
    }

    private function tenant(string $id = 'acme', array $extra = []): Tenant
    {
        // Sans événements : on ne crée pas de vrai schéma PostgreSQL dans un test.
        return Tenant::withoutEvents(fn () => Tenant::create($extra + ['id' => $id, 'name' => 'Acme', 'status' => 'active']));
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $this->get('/console/dashboard')->assertRedirect('/console/login');
        $this->get('/console/journal')->assertRedirect('/console/login');
    }

    public function test_mauvais_identifiants_refuses_et_journalises(): void
    {
        $this->admin();

        $this->post('/console/login', ['email' => 'admin@test.local', 'password' => 'faux'])->assertSessionHasErrors('email');
        $this->post('/console/login', ['email' => 'inconnu@test.local', 'password' => 'faux'])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        $this->assertSame(2, AuditLog::where('action', 'auth.login_failed')->count());
    }

    public function test_connexion_reussie_sans_2fa_pour_un_admin_sans_secret(): void
    {
        PlatformAdmin::create(['name' => 'A', 'email' => 'a@test.local', 'password' => 'Password-12345', 'role' => 'admin']);

        $this->post('/console/login', ['email' => 'a@test.local', 'password' => 'Password-12345'])->assertRedirect('/console/dashboard');
        $this->assertAuthenticated('admin');
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login']);
    }

    public function test_avec_2fa_le_mot_de_passe_ne_suffit_pas(): void
    {
        $this->admin();

        $this->post('/console/login', ['email' => 'admin@test.local', 'password' => 'Password-12345'])->assertRedirect('/console/verification');
        $this->assertGuest('admin');
        $this->get('/console/dashboard')->assertRedirect('/console/login');
    }

    public function test_un_compte_desactive_ne_peut_pas_entrer(): void
    {
        $this->admin(extra: ['is_active' => false]);

        $this->post('/console/login', ['email' => 'admin@test.local', 'password' => 'Password-12345'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_un_super_admin_sans_2fa_est_force_de_l_activer(): void
    {
        $super = $this->admin('super_admin', ['two_factor_secret' => null, 'two_factor_confirmed_at' => null]);

        $this->actingAs($super, 'admin')->get('/console/dashboard')->assertRedirect('/console/double-authentification');
        $this->actingAs($super, 'admin')->get('/console/double-authentification')->assertOk();
    }

    public function test_un_mot_de_passe_provisoire_doit_etre_change(): void
    {
        $admin = $this->admin(extra: ['must_change_password' => true]);

        $this->actingAs($admin, 'admin')->get('/console/entreprises')->assertRedirect('/console/mot-de-passe');
        $this->actingAs($admin, 'admin')->get('/console/mot-de-passe')->assertOk();
    }

    public function test_un_compte_desactive_en_cours_de_session_est_deconnecte(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->get('/console/dashboard')->assertOk();

        $admin->update(['is_active' => false]);

        $this->actingAs($admin->fresh(), 'admin')->get('/console/dashboard')->assertRedirect('/console/login');
    }

    public function test_droits_du_role_support(): void
    {
        $this->tenant();
        $this->actingAs($this->admin('support'), 'admin');

        $this->get('/console/entreprises')->assertOk();
        $this->get('/console/entreprises/acme')->assertOk();
        $this->get('/console/entreprises/nouvelle')->assertForbidden();
        $this->post('/console/entreprises/acme/suspendre', ['reason' => 'x', 'confirm' => 'acme'])->assertForbidden();
        $this->get('/console/journal')->assertForbidden();
        $this->get('/console/equipe')->assertForbidden();
    }

    public function test_droits_du_role_administrateur(): void
    {
        $this->tenant();
        $this->actingAs($this->admin('admin'), 'admin');

        $this->get('/console/entreprises/nouvelle')->assertOk();
        $this->post('/console/entreprises/acme/suspendre', ['reason' => 'Impayé', 'confirm' => 'acme'])->assertRedirect();
        $this->post('/console/entreprises/acme/bannir', ['reason' => 'x', 'confirm' => 'acme'])->assertForbidden();
        $this->get('/console/journal')->assertForbidden();
        $this->get('/console/equipe')->assertForbidden();
    }

    public function test_le_super_admin_a_tous_les_droits(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin');

        $this->get('/console/journal')->assertOk();
        $this->get('/console/equipe')->assertOk();
        $this->get('/console/entreprises/nouvelle')->assertOk();
    }

    public function test_suspension_exige_le_motif_et_la_confirmation(): void
    {
        $this->tenant();
        $this->actingAs($this->admin('super_admin'), 'admin');

        $this->post('/console/entreprises/acme/suspendre', ['reason' => 'Impayé', 'confirm' => 'mauvais'])->assertSessionHasErrors('confirm');
        $this->post('/console/entreprises/acme/suspendre', ['reason' => '', 'confirm' => 'acme'])->assertSessionHasErrors('reason');
        $this->assertSame('active', Tenant::find('acme')->status);

        $this->post('/console/entreprises/acme/suspendre', ['reason' => 'Impayé', 'confirm' => 'acme'])->assertRedirect();
        $this->assertSame('suspended', Tenant::find('acme')->status);
        $this->assertSame('Impayé', Tenant::find('acme')->suspended_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.suspend', 'tenant_id' => 'acme']);
    }

    /** Exécute le middleware comme si le tenant était identifié (sans ouvrir de vraie connexion d'entreprise). */
    private function runTenantMiddleware(Tenant $tenant): \Symfony\Component\HttpFoundation\Response
    {
        app()->instance(\Stancl\Tenancy\Contracts\Tenant::class, $tenant);

        return (new \App\Http\Middleware\EnsureTenantIsActive)->handle(
            \Illuminate\Http\Request::create('/api/login', 'POST'),
            fn () => response('passé'),
        );
    }

    public function test_une_entreprise_suspendue_est_bloquee_cote_api(): void
    {
        $response = $this->runTenantMiddleware($this->tenant('acme', ['status' => 'suspended', 'suspended_reason' => 'Impayé']));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('tenant_suspended', json_decode($response->getContent(), true)['code']);
        $this->assertSame('Impayé', json_decode($response->getContent(), true)['reason']);
    }

    public function test_une_entreprise_bannie_est_bloquee_cote_api(): void
    {
        $response = $this->runTenantMiddleware($this->tenant('acme', ['status' => 'banned']));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('tenant_banned', json_decode($response->getContent(), true)['code']);
    }

    public function test_une_entreprise_active_n_est_pas_bloquee(): void
    {
        $response = $this->runTenantMiddleware($this->tenant('acme'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('passé', $response->getContent());
    }

    public function test_la_route_api_des_entreprises_applique_le_blocage(): void
    {
        $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->uri() === 'api/login' && in_array('POST', $r->methods()));

        $this->assertContains('tenant.active', $route->gatherMiddleware());
    }

    public function test_retablir_une_entreprise_efface_la_suspension(): void
    {
        $this->tenant('acme', ['status' => 'suspended', 'suspended_reason' => 'Impayé']);
        $this->actingAs($this->admin('admin'), 'admin');

        $this->post('/console/entreprises/acme/retablir')->assertRedirect();

        $tenant = Tenant::find('acme');
        $this->assertSame('active', $tenant->status);
        $this->assertNull($tenant->suspended_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.reactivate', 'tenant_id' => 'acme']);
    }

    public function test_on_ne_peut_ni_se_retrograder_ni_supprimer_le_dernier_super_admin(): void
    {
        $super = $this->admin('super_admin');
        $this->actingAs($super, 'admin');

        $this->put("/console/equipe/{$super->id}", ['role' => 'admin', 'is_active' => 1])->assertSessionHasErrors('team');
        $this->put("/console/equipe/{$super->id}", ['role' => 'super_admin', 'is_active' => 0])->assertSessionHasErrors('team');
        $this->assertSame('super_admin', $super->fresh()->role);
    }

    public function test_creation_d_un_membre_genere_un_mot_de_passe_provisoire_a_changer(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin');

        $this->post('/console/equipe', ['name' => 'Nouveau', 'email' => 'nouveau@test.local', 'role' => 'support'])
            ->assertSessionHas('temp_password');

        $member = PlatformAdmin::where('email', 'nouveau@test.local')->first();
        $this->assertTrue($member->must_change_password);
        $this->assertSame('support', $member->role);
    }

    public function test_commande_de_creation_d_admin(): void
    {
        $this->artisan('console:create-admin', ['email' => 'boss@test.local', '--password' => 'Password-12345'])->assertSuccessful();
        $this->assertDatabaseHas('platform_admins', ['email' => 'boss@test.local', 'role' => 'super_admin']);

        $this->artisan('console:create-admin', ['email' => 'boss@test.local'])->assertFailed();
        $this->artisan('console:create-admin', ['email' => 'x@test.local', '--role' => 'root'])->assertFailed();
    }

    public function test_l_export_csv_neutralise_les_formules(): void
    {
        $this->assertSame("'=SOMME(A1)", \App\Http\Controllers\Console\AuditController::safe('=SOMME(A1)'));
        $this->assertSame("'@cmd", \App\Http\Controllers\Console\AuditController::safe('@cmd'));
        $this->assertSame('texte normal', \App\Http\Controllers\Console\AuditController::safe('texte normal'));
    }

    public function test_l_api_inconnue_renvoie_une_vraie_404_et_pas_l_interface(): void
    {
        $this->getJson('/api/nimporte-quoi')->assertNotFound();
        $this->get('/assets/ancien-fichier.js')->assertNotFound();
    }
}
