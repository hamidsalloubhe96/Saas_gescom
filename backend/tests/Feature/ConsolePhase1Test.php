<?php

namespace Tests\Feature;

use App\Http\Middleware\ImpersonationReadOnly;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/** Phase 1 : annuaire des utilisateurs, assistance (impersonation), modification d'une entreprise. */
class ConsolePhase1Test extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin'): PlatformAdmin
    {
        $a = PlatformAdmin::create(['name' => ucfirst($role), 'email' => "$role@test.local", 'password' => 'Password-12345', 'role' => $role, 'is_active' => true]);
        $a->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $a->fresh();
    }

    private function tenant(string $id = 'acme', array $extra = []): Tenant
    {
        return Tenant::withoutEvents(fn () => Tenant::create($extra + ['id' => $id, 'name' => 'Acme', 'status' => 'active']));
    }

    private function entry(string $tenant = 'acme', int $user = 1, string $email = 'ali@acme.test', array $extra = []): TenantUser
    {
        return TenantUser::create($extra + ['tenant_id' => $tenant, 'user_id' => $user, 'name' => 'Ali', 'email' => $email, 'role' => 'admin', 'is_active' => true]);
    }

    public function test_la_recherche_globale_trouve_par_email_et_filtre(): void
    {
        $this->tenant('acme');
        $this->tenant('zeta', ['name' => 'Zeta']);
        $this->entry('acme', 1, 'ali@acme.test');
        $this->entry('zeta', 1, 'zoe@zeta.test', ['name' => 'Zoé', 'is_active' => false]);
        $this->actingAs($this->admin('support'), 'admin');

        $this->get('/console/utilisateurs?q=ali@')->assertOk()->assertSee('ali@acme.test')->assertDontSee('zoe@zeta.test');
        $this->get('/console/utilisateurs?status=inactive')->assertOk()->assertSee('zoe@zeta.test')->assertDontSee('ali@acme.test');
        $this->get('/console/utilisateurs?tenant=zeta')->assertOk()->assertSee('zoe@zeta.test');
    }

    public function test_les_recherches_speciales_ne_cassent_rien(): void
    {
        $this->actingAs($this->admin('support'), 'admin');
        $this->get('/console/utilisateurs?q='.urlencode("%' OR 1=1 --"))->assertOk();
        $this->get('/console/utilisateurs?q=%25')->assertOk()->assertSee('Aucun utilisateur');
    }

    public function test_droits_sur_les_actions_utilisateur(): void
    {
        $this->tenant();
        $e = $this->entry();

        $this->actingAs($this->admin('support'), 'admin');
        $this->post("/console/utilisateurs/{$e->id}/activer")->assertForbidden();
        $this->post("/console/utilisateurs/{$e->id}/deconnecter")->assertForbidden();
        $this->post("/console/utilisateurs/{$e->id}/mot-de-passe")->assertForbidden();
    }

    public function test_le_droit_de_modification_est_reserve_au_super_admin(): void
    {
        $this->tenant();
        $e = $this->entry();

        // Un administrateur demande le mode « write » : refusé avant tout accès à l'entreprise.
        $this->actingAs($this->admin('admin'), 'admin');
        $this->post("/console/utilisateurs/{$e->id}/assistance", ['mode' => 'write', 'reason' => 'Dépannage urgent'])->assertForbidden();
    }

    public function test_assistance_refusee_sur_entreprise_suspendue_ou_compte_desactive(): void
    {
        $this->tenant('acme', ['status' => 'suspended']);
        $e = $this->entry();
        $this->actingAs($this->admin('support'), 'admin');

        $this->post("/console/utilisateurs/{$e->id}/assistance", ['mode' => 'read'])->assertSessionHasErrors('impersonate');

        $this->tenant('beta', ['name' => 'Beta']);
        $off = $this->entry('beta', 1, 'off@beta.test', ['is_active' => false]);
        $this->post("/console/utilisateurs/{$off->id}/assistance", ['mode' => 'read'])->assertSessionHasErrors('impersonate');
    }

    public function test_le_code_d_assistance_ne_sert_qu_une_fois(): void
    {
        $this->tenant();
        $code = str_repeat('a', 48);
        Cache::put('impersonation:'.hash('sha256', $code), ['tenant' => 'acme', 'token' => Crypt::encryptString('1|secret'), 'read_only' => true], 60);

        $this->postJson('/api/impersonation/exchange', ['code' => $code])
            ->assertOk()->assertJsonPath('token', '1|secret')->assertJsonPath('tenant', 'acme')->assertJsonPath('read_only', true);

        $this->postJson('/api/impersonation/exchange', ['code' => $code])->assertStatus(410);
    }

    public function test_code_inconnu_ou_mal_forme_refuse(): void
    {
        $this->postJson('/api/impersonation/exchange', ['code' => 'trop-court'])->assertStatus(422);
        $this->postJson('/api/impersonation/exchange', ['code' => str_repeat('z', 48)])->assertStatus(410);
    }

    public function test_un_code_ne_donne_pas_acces_a_une_entreprise_suspendue(): void
    {
        $this->tenant('acme', ['status' => 'suspended']);
        $code = str_repeat('b', 48);
        Cache::put('impersonation:'.hash('sha256', $code), ['tenant' => 'acme', 'token' => Crypt::encryptString('1|x'), 'read_only' => true], 60);

        $this->postJson('/api/impersonation/exchange', ['code' => $code])->assertForbidden();
    }

    private function requestWithToken(string $method, string $name, array $abilities): Request
    {
        $token = (object) ['name' => $name, 'abilities' => $abilities];
        $user = new class($token)
        {
            public function __construct(private object $t) {}

            public function currentAccessToken(): object
            {
                return $this->t;
            }
        };

        $request = Request::create('/api/documents', $method);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_une_assistance_en_lecture_seule_ne_peut_rien_modifier(): void
    {
        $mw = new ImpersonationReadOnly;
        $next = fn () => response('passé');

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $r = $mw->handle($this->requestWithToken($method, 'impersonation', ['read-only']), $next);
            $this->assertSame(403, $r->getStatusCode(), $method);
            $this->assertSame('impersonation_read_only', json_decode($r->getContent(), true)['code']);
        }
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $this->assertSame(200, $mw->handle($this->requestWithToken($method, 'impersonation', ['read-only']), $next)->getStatusCode(), $method);
        }
    }

    public function test_l_assistance_avec_modification_et_les_jetons_normaux_ne_sont_pas_bloques(): void
    {
        $mw = new ImpersonationReadOnly;
        $next = fn () => response('passé');

        $this->assertSame(200, $mw->handle($this->requestWithToken('POST', 'impersonation', ['*']), $next)->getStatusCode());
        $this->assertSame(200, $mw->handle($this->requestWithToken('POST', 'spa', ['*']), $next)->getStatusCode());
        // Un jeton normal dont le nom ressemblerait à « impersonation » mais avec droits complets reste libre.
        $this->assertSame(200, $mw->handle($this->requestWithToken('DELETE', 'spa', ['read-only']), $next)->getStatusCode());
    }

    public function test_la_route_de_l_api_applique_la_lecture_seule(): void
    {
        $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->uri() === 'api/documents' && in_array('POST', $r->methods()));
        $this->assertContains('impersonation.readonly', $route->gatherMiddleware());
    }

    public function test_modifier_une_entreprise(): void
    {
        $this->tenant();
        $this->actingAs($this->admin('admin'), 'admin');

        $this->get('/console/entreprises/acme/modifier')->assertOk();
        $this->put('/console/entreprises/acme', ['company' => 'Acme Corp', 'owner_email' => 'boss@acme.test', 'country' => 'Mali', 'city' => 'Bamako'])
            ->assertRedirect('/console/entreprises/acme');

        $t = Tenant::find('acme');
        $this->assertSame('Acme Corp', $t->name);
        $this->assertSame('boss@acme.test', $t->owner_email);
        $this->assertSame('Bamako', $t->city);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.update', 'tenant_id' => 'acme']);

        $this->put('/console/entreprises/acme', ['company' => '', 'owner_email' => 'pas-un-email'])->assertSessionHasErrors(['company', 'owner_email']);
    }

    public function test_le_support_ne_peut_pas_modifier_une_entreprise(): void
    {
        $this->tenant();
        $this->actingAs($this->admin('support'), 'admin');

        $this->get('/console/entreprises/acme/modifier')->assertForbidden();
        $this->put('/console/entreprises/acme', ['company' => 'X'])->assertForbidden();
    }

    public function test_changer_le_mot_de_passe_d_un_membre_ferme_ses_autres_sessions(): void
    {
        $victim = $this->admin('support');
        $this->actingAs($victim, 'admin')->get('/console/dashboard')->assertOk();
        $hash = session('password_hash_admin');
        $this->assertNotNull($hash, 'L\'empreinte du mot de passe doit être gardée en session');

        // Un super admin réinitialise le mot de passe de ce compte depuis ailleurs.
        $victim->forceFill(['password' => 'Autre-Mot-De-Passe-9'])->save();

        $this->actingAs($victim->fresh(), 'admin')->withSession(['password_hash_admin' => $hash])
            ->get('/console/dashboard')->assertRedirect('/console/login');
    }
}
