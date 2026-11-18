<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use App\Models\Review;
use App\Models\SignupRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 3 : tableau de bord, demandes d'inscription, avis, annonces. */
class ConsolePhase3Test extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin'): PlatformAdmin
    {
        $a = PlatformAdmin::create(['name' => ucfirst($role), 'email' => "$role@test.local", 'password' => 'Password-12345', 'role' => $role, 'is_active' => true]);
        $a->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $a->fresh();
    }

    private function tenant(string $id, array $extra = []): Tenant
    {
        $t = Tenant::withoutEvents(fn () => Tenant::create($extra + ['id' => $id, 'name' => ucfirst($id), 'status' => 'active']));
        app(SubscriptionService::class)->startTrial($t);

        return $t->fresh();
    }

    // ---------- Tableau de bord ----------

    public function test_le_tableau_de_bord_calcule_mrr_arr_et_encaissements(): void
    {
        $plans = \App\Models\Plan::pluck('id', 'slug');
        $a = $this->tenant('alpha', ['country' => 'Sénégal']);
        $b = $this->tenant('beta', ['country' => 'Mali']);
        $svc = app(SubscriptionService::class);
        $svc->activate($a, \App\Models\Plan::find($plans['standard']));   // 25 000 / mois
        $svc->activate($b, \App\Models\Plan::find($plans['petite']));     // 10 000 / mois
        $admin = $this->admin('super_admin');
        $svc->recordOfflinePayment($a, \App\Models\Plan::find($plans['standard']), ['amount' => 25000, 'method' => 'cash', 'months' => 1], $admin);

        $this->actingAs($admin, 'admin')->get('/console/dashboard')->assertOk()
            ->assertSee('35 000 XOF')                // MRR = 25 000 + 10 000
            ->assertSee('420 000 XOF')               // ARR = MRR × 12
            ->assertSee('25 000 XOF')                // encaissé sur la période
            ->assertSee('2 client(s) payant(s)');
    }

    public function test_les_filtres_du_tableau_de_bord_changent_le_perimetre(): void
    {
        $plans = \App\Models\Plan::pluck('id', 'slug');
        $svc = app(SubscriptionService::class);
        $svc->activate($this->tenant('alpha', ['country' => 'Sénégal']), \App\Models\Plan::find($plans['standard']));
        $svc->activate($this->tenant('beta', ['country' => 'Mali']), \App\Models\Plan::find($plans['petite']));
        $this->actingAs($this->admin('super_admin'), 'admin');

        $this->get('/console/dashboard?country=Mali')->assertOk()->assertSee('10 000 XOF')->assertDontSee('35 000 XOF');
        $this->get('/console/dashboard?plan='.$plans['standard'])->assertOk()->assertSee('25 000 XOF')->assertDontSee('35 000 XOF');
        $this->get('/console/dashboard?period=7&plan='.$plans['pro'])->assertOk()->assertSee('0 XOF');
        $this->get('/console/dashboard?period=nimporte')->assertSessionHasErrors('period');
    }

    public function test_a_traiter_liste_les_actions_avec_leurs_liens(): void
    {
        $t = $this->tenant('alpha');
        app(SubscriptionService::class)->declarePayment($t, ['amount' => 100, 'method' => 'cash']);
        $t->subscription->update(['trial_ends_at' => now()->addDays(2)]);
        SignupRequest::create(['name' => 'Aïcha', 'email' => 'a@x.co', 'company' => 'Boutique A']);
        Review::create(['author_name' => 'Ali', 'rating' => 5, 'body' => 'Très bien, merci !', 'status' => 'pending']);

        $this->actingAs($this->admin('admin'), 'admin')->get('/console/dashboard')->assertOk()
            ->assertSee('1 paiement à valider')->assertSee(route('console.payments.index'), false)
            ->assertSee('1 essai se termine dans 3 jours')
            ->assertSee('1 demande d\'inscription')->assertSee('1 avis à relire');
    }

    public function test_a_traiter_est_vide_quand_tout_va_bien(): void
    {
        $this->actingAs($this->admin('admin'), 'admin')->get('/console/dashboard')->assertOk()->assertSee('Rien à traiter');
    }

    public function test_le_tableau_de_bord_s_affiche_pour_chaque_role(): void
    {
        foreach (['support', 'admin', 'super_admin'] as $role) {
            $this->flushSession();
            $this->actingAs($this->admin($role), 'admin')->get('/console/dashboard')->assertOk();
            PlatformAdmin::query()->delete();
        }
    }

    // ---------- Demandes d'inscription ----------

    private function signup(array $over = []): array
    {
        return $over + ['name' => 'Moussa', 'email' => 'moussa@boutique.test', 'company' => 'Boutique Moussa', 'phone' => '770000000', 'country' => 'Sénégal', 'message' => 'Je veux essayer.'];
    }

    public function test_une_demande_publique_est_enregistree(): void
    {
        $this->postJson('/api/signup-requests', $this->signup())->assertCreated();

        $r = SignupRequest::firstWhere('email', 'moussa@boutique.test');
        $this->assertSame('new', $r->status);
        $this->assertSame('Boutique Moussa', $r->company);
    }

    public function test_le_champ_piege_des_robots_n_enregistre_rien(): void
    {
        $this->postJson('/api/signup-requests', $this->signup(['website' => 'http://spam.example']))->assertCreated();
        $this->assertSame(0, SignupRequest::count());
    }

    public function test_une_meme_adresse_ne_peut_pas_inonder_la_file(): void
    {
        $this->postJson('/api/signup-requests', $this->signup())->assertCreated();
        $this->postJson('/api/signup-requests', $this->signup(['company' => 'Autre']))->assertCreated();
        $this->assertSame(1, SignupRequest::count(), 'une demande par heure et par e-mail');
    }

    public function test_validation_et_limitation_du_formulaire_public(): void
    {
        $this->postJson('/api/signup-requests', ['name' => '', 'email' => 'pas-un-email'])->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'company']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/signup-requests', $this->signup(['email' => "u{$i}@x.test"]));
        }
        $this->postJson('/api/signup-requests', $this->signup(['email' => 'trop@x.test']))->assertStatus(429);
    }

    public function test_traiter_une_demande_depuis_la_console(): void
    {
        $r = SignupRequest::create($this->signup());
        $this->actingAs($this->admin('admin'), 'admin');

        $this->get('/console/demandes')->assertOk()->assertSee('Boutique Moussa');
        $this->patch("/console/demandes/{$r->id}", ['status' => 'contacted'])->assertRedirect();
        $this->assertSame('contacted', $r->fresh()->status);
        $this->patch("/console/demandes/{$r->id}", ['status' => 'converted'])->assertSessionHasErrors('status');
        $this->get('/console/demandes?status=contacted')->assertOk()->assertSee('Boutique Moussa');
    }

    public function test_creer_une_entreprise_depuis_une_demande_prerempli_le_formulaire(): void
    {
        $r = SignupRequest::create($this->signup());
        $this->actingAs($this->admin('admin'), 'admin');

        $this->get('/console/entreprises/nouvelle?request='.$r->id)->assertOk()
            ->assertSee('Boutique Moussa')->assertSee('moussa@boutique.test')->assertSee('name="request_id"', false);
    }

    public function test_le_support_ne_voit_pas_les_demandes_ni_les_avis_ni_les_annonces(): void
    {
        $this->actingAs($this->admin('support'), 'admin');

        $this->get('/console/demandes')->assertForbidden();
        $this->get('/console/avis')->assertForbidden();
        $this->get('/console/annonces')->assertForbidden();
    }

    // ---------- Avis ----------

    public function test_seuls_les_avis_publies_sont_publics(): void
    {
        Review::create(['tenant_id' => 'secret', 'author_name' => 'Ali', 'company' => 'Acme', 'rating' => 5, 'body' => 'Excellent outil.', 'status' => 'published', 'published_at' => now()]);
        Review::create(['author_name' => 'Zoé', 'rating' => 1, 'body' => 'Pas encore relu.', 'status' => 'pending']);
        Review::create(['author_name' => 'Max', 'rating' => 2, 'body' => 'Masqué par l\'équipe.', 'status' => 'hidden']);

        $json = $this->getJson('/api/public/reviews')->assertOk()->assertJsonCount(1)->json();

        $this->assertSame('Ali', $json[0]['author_name']);
        $this->assertArrayNotHasKey('tenant_id', $json[0], 'l\'identifiant de l\'entreprise ne doit jamais fuiter');
        $this->assertArrayNotHasKey('status', $json[0]);
    }

    public function test_publier_et_masquer_un_avis(): void
    {
        $r = Review::create(['author_name' => 'Ali', 'company' => 'Acme', 'rating' => 4, 'body' => 'Très pratique au quotidien.']);
        $this->actingAs($this->admin('admin'), 'admin');

        $this->get('/console/avis')->assertOk()->assertSee('À relire (1)');
        $this->patch("/console/avis/{$r->id}", ['status' => 'published'])->assertRedirect();
        $this->assertSame('published', $r->fresh()->status);
        $this->assertNotNull($r->fresh()->published_at);

        $this->patch("/console/avis/{$r->id}", ['status' => 'hidden'])->assertRedirect();
        $this->assertSame('hidden', $r->fresh()->status);
        $this->assertNull($r->fresh()->published_at);
        $this->patch("/console/avis/{$r->id}", ['status' => 'nimporte'])->assertSessionHasErrors('status');
    }

    public function test_la_route_d_envoi_d_un_avis_exige_une_connexion_et_est_limitee(): void
    {
        $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->uri() === 'api/reviews' && in_array('POST', $r->methods()));

        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        $this->assertContains('throttle:5,60', $route->gatherMiddleware());
    }

    // ---------- Annonces ----------

    public function test_creer_activer_et_supprimer_une_annonce(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin');

        $this->post('/console/annonces', ['title' => 'Nouvelle version', 'body' => 'La caisse est plus rapide.', 'level' => 'update'])->assertRedirect();
        $a = Announcement::firstWhere('title', 'Nouvelle version');
        $this->assertTrue($a->is_active);
        $this->assertSame(1, Announcement::current()->count());

        $this->patch("/console/annonces/{$a->id}/actif")->assertRedirect();
        $this->assertSame(0, Announcement::current()->count(), 'désactivée = invisible');

        $this->delete("/console/annonces/{$a->id}")->assertRedirect();
        $this->assertSame(0, Announcement::count());
    }

    public function test_validation_des_annonces_et_droits(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin');
        $this->post('/console/annonces', ['title' => '', 'level' => 'critique'])->assertSessionHasErrors(['title', 'level']);
        $this->post('/console/annonces', ['title' => 'X', 'level' => 'info', 'ends_at' => '2020-01-01 10:00'])->assertSessionHasErrors('ends_at');

        $this->flushSession();
        $this->actingAs($this->admin('admin'), 'admin')->get('/console/annonces')->assertForbidden();
    }

    public function test_une_annonce_respecte_sa_fenetre_de_dates(): void
    {
        Announcement::create(['title' => 'En cours', 'level' => 'info', 'is_active' => true]);
        Announcement::create(['title' => 'Plus tard', 'level' => 'info', 'is_active' => true, 'starts_at' => now()->addDay()]);
        Announcement::create(['title' => 'Terminée', 'level' => 'info', 'is_active' => true, 'ends_at' => now()->subDay()]);
        Announcement::create(['title' => 'Inactive', 'level' => 'info', 'is_active' => false]);

        $this->assertSame(['En cours'], Announcement::current()->pluck('title')->all());
    }

    // ---------- Inscriptions ----------

    public function test_la_configuration_publique_ne_contient_rien_de_sensible(): void
    {
        PlatformSetting::put('signup_open', false);

        $json = $this->getJson('/api/public/config')->assertOk()->json();

        $this->assertFalse($json['signup_open']);
        $this->assertEqualsCanonicalizing(['signup_open', 'turnstile_site_key', 'trial_days', 'email_verification', 'contact_email', 'contact_phone'], array_keys($json));
    }

    public function test_inscription_libre_fermee_depuis_la_console(): void
    {
        PlatformSetting::put('signup_open', false);

        $this->postJson('/api/register-tenant', [
            'company' => 'X', 'slug' => 'xx_corp', 'name' => 'X', 'email' => 'x@x.co', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertForbidden()->assertJsonFragment(['message' => 'Les inscriptions sont fermées pour le moment. Demandez un accès et nous vous recontactons.']);
        $this->assertSame(0, Tenant::count());
    }
}
