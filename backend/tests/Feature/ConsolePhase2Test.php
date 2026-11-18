<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantIsActive;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\PlanLimits;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Phase 2 : forfaits, abonnements, paiements, limites. */
class ConsolePhase2Test extends TestCase
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
        $t = Tenant::withoutEvents(fn () => Tenant::create($extra + ['id' => $id, 'name' => 'Acme', 'status' => 'active']));
        app(SubscriptionService::class)->startTrial($t);

        return $t->fresh();
    }

    private function plan(string $slug = 'standard'): Plan
    {
        return Plan::where('slug', $slug)->firstOrFail();
    }

    // ---------- Forfaits ----------

    public function test_les_forfaits_par_defaut_existent(): void
    {
        $this->assertSame(['essai', 'petite', 'standard', 'pro'], Plan::orderBy('sort')->pluck('slug')->all());
        $this->assertTrue($this->plan('essai')->is_trial);
        $this->assertNull($this->plan('pro')->max_products, 'illimité = NULL');
    }

    public function test_creer_un_forfait_avec_paliers(): void
    {
        $this->actingAs($this->admin('admin'), 'admin');

        $this->post('/console/forfaits', [
            'slug' => 'gold', 'name' => 'Gold', 'price' => 80000, 'currency' => 'xof', 'period_months' => 1,
            'max_users' => '', 'max_products' => '', 'max_invoices_month' => 5000, 'tiers' => "25=9000\n10=5000", 'is_active' => 1,
        ])->assertRedirect('/console/forfaits');

        $gold = Plan::firstWhere('slug', 'gold');
        $this->assertSame('XOF', $gold->currency);
        $this->assertNull($gold->max_users);
        $this->assertSame(5000, $gold->max_invoices_month);
        $this->assertEquals([['up_to' => 10, 'price' => 5000], ['up_to' => 25, 'price' => 9000]], $gold->tiers, 'paliers triés par effectif');
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.create']);
    }

    public function test_validation_d_un_forfait(): void
    {
        $this->actingAs($this->admin('admin'), 'admin');

        $this->post('/console/forfaits', ['slug' => 'Mauvais Slug', 'name' => '', 'price' => -5, 'currency' => 'EURO', 'period_months' => 0])
            ->assertSessionHasErrors(['slug', 'name', 'price', 'currency', 'period_months']);
        $this->post('/console/forfaits', ['slug' => 'standard', 'name' => 'Doublon', 'price' => 1, 'currency' => 'XOF', 'period_months' => 1])
            ->assertSessionHasErrors('slug');
        $this->post('/console/forfaits', ['slug' => 'ok', 'name' => 'Ok', 'price' => 1, 'currency' => 'XOF', 'period_months' => 1, 'tiers' => 'n importe quoi'])
            ->assertSessionHasErrors('tiers');
        $this->post('/console/forfaits', ['slug' => 'essai2', 'name' => 'E', 'price' => 0, 'currency' => 'XOF', 'period_months' => 1, 'is_trial' => 1])
            ->assertSessionHasErrors('trial_days');
    }

    public function test_desactiver_un_forfait_ne_touche_pas_aux_abonnes(): void
    {
        $t = $this->tenant();
        $this->subscriptions()->activate($t, $this->plan('petite'));
        $this->actingAs($this->admin('admin'), 'admin');

        $this->patch('/console/forfaits/'.$this->plan('petite')->id.'/actif')->assertRedirect();

        $this->assertFalse($this->plan('petite')->is_active);
        $this->assertSame($this->plan('petite')->id, Subscription::firstWhere('tenant_id', 'acme')->plan_id);
    }

    public function test_le_support_peut_voir_les_forfaits_mais_pas_les_modifier(): void
    {
        $this->actingAs($this->admin('support'), 'admin');

        $this->get('/console/forfaits')->assertOk();
        $this->get('/console/forfaits/nouveau')->assertForbidden();
        $this->get('/console/paiements')->assertForbidden();
    }

    private function subscriptions(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    // ---------- Cycle de vie ----------

    public function test_une_nouvelle_entreprise_demarre_en_essai(): void
    {
        PlatformSetting::put('trial_days', 10);
        $sub = $this->tenant()->subscription;

        $this->assertSame('trial', $sub->status);
        $this->assertTrue($sub->plan->is_trial);
        $this->assertEqualsWithDelta(10, $sub->daysLeft(), 1);
    }

    public function test_essai_termine_suspend_l_entreprise(): void
    {
        $t = $this->tenant();
        $t->subscription->update(['trial_ends_at' => now()->subDay()]);

        $r = $this->subscriptions()->refresh();

        $this->assertSame(1, $r['expired']);
        $this->assertSame('expired', $t->subscription->fresh()->status);
        $t->refresh();
        $this->assertSame('suspended', $t->status);
        $this->assertSame('subscription', $t->suspension_source);
    }

    public function test_echeance_depassee_puis_grace_puis_expiration(): void
    {
        PlatformSetting::put('grace_days', 5);
        $t = $this->tenant();
        $this->subscriptions()->activate($t, $this->plan('standard'), 1);
        $sub = $t->subscription->fresh();

        // 1. Période payée échue -> en retard, avec 5 jours de grâce, l'accès n'est PAS encore coupé.
        $sub->update(['current_period_end' => now()->subDay()]);
        $this->assertSame(1, $this->subscriptions()->refresh()['past_due']);
        $sub->refresh();
        $this->assertSame('past_due', $sub->status);
        $this->assertTrue($sub->grace_ends_at->isFuture());
        $this->assertSame('active', $t->fresh()->status);

        // 2. Grâce écoulée -> expiré + suspension.
        $sub->update(['grace_ends_at' => now()->subHour()]);
        $this->assertSame(1, $this->subscriptions()->refresh()['expired']);
        $this->assertSame('suspended', $t->fresh()->status);
        $this->assertSame('Abonnement impayé', $t->fresh()->suspended_reason);
    }

    public function test_un_paiement_valide_prolonge_et_rouvre_l_acces(): void
    {
        $t = $this->tenant();
        $t->subscription->update(['trial_ends_at' => now()->subDay()]);
        $this->subscriptions()->refresh();
        $this->assertSame('suspended', $t->fresh()->status);

        $payment = $this->subscriptions()->declarePayment($t, ['plan_id' => $this->plan('standard')->id, 'amount' => 25000, 'months' => 2, 'method' => 'transfer', 'reference' => 'VIR-1']);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('suspended', $t->fresh()->status, 'Un paiement seulement déclaré ne rouvre rien');

        $this->subscriptions()->validatePayment($payment, $this->admin());

        $t->refresh();
        $sub = $t->subscription->fresh();
        $this->assertSame('active', $t->status);
        $this->assertNull($t->suspension_source);
        $this->assertSame('active', $sub->status);
        $this->assertEqualsWithDelta(60, $sub->daysLeft(), 2, '2 périodes d\'un mois');
        $this->assertSame($this->plan('standard')->id, $sub->plan_id);
    }

    public function test_un_paiement_ne_se_traite_qu_une_fois(): void
    {
        $t = $this->tenant();
        $admin = $this->admin();
        $p = $this->subscriptions()->declarePayment($t, ['amount' => 100, 'method' => 'cash']);
        $this->subscriptions()->validatePayment($p, $admin);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->subscriptions()->validatePayment($p->fresh(), $admin);
    }

    public function test_un_paiement_prolonge_a_partir_de_l_echeance_et_non_d_aujourd_hui(): void
    {
        $t = $this->tenant();
        $this->subscriptions()->activate($t, $this->plan('standard'), 1);
        $end = $t->subscription->fresh()->current_period_end->copy();

        $this->subscriptions()->activate($t, $this->plan('standard'), 1);

        $this->assertEquals($end->copy()->addMonth()->toDateString(), $t->subscription->fresh()->current_period_end->toDateString(), 'pas de jours perdus quand on paie en avance');
    }

    public function test_la_suspension_par_la_console_n_est_pas_levee_par_un_paiement(): void
    {
        $t = $this->tenant();
        $t->forceFill(['status' => 'suspended', 'suspension_source' => 'console', 'suspended_reason' => 'Fraude'])->save();

        $this->subscriptions()->activate($t, $this->plan('standard'), 1);

        $this->assertSame('suspended', $t->fresh()->status, 'Seule une suspension d\'abonnement est levée automatiquement');
    }

    public function test_la_suspension_d_abonnement_ne_remplace_pas_une_suspension_console(): void
    {
        $t = $this->tenant();
        $t->forceFill(['status' => 'suspended', 'suspension_source' => 'console', 'suspended_reason' => 'Fraude'])->save();
        $t->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->subscriptions()->refresh();

        $t->refresh();
        $this->assertSame('console', $t->suspension_source);
        $this->assertSame('Fraude', $t->suspended_reason);
    }

    public function test_jours_offerts_sur_un_essai_et_sur_un_abonnement_expire(): void
    {
        $t = $this->tenant();
        $before = $t->subscription->trial_ends_at->copy();
        $this->subscriptions()->offerDays($t, 5);
        $this->assertEquals($before->addDays(5)->toDateString(), $t->subscription->fresh()->trial_ends_at->toDateString());

        // Expiré + suspendu : les jours offerts rouvrent l'accès.
        $t->subscription->update(['status' => 'expired']);
        $t->forceFill(['status' => 'suspended', 'suspension_source' => 'subscription'])->save();
        $this->subscriptions()->offerDays($t, 3);

        $this->assertSame('active', $t->fresh()->status);
        $this->assertSame('active', $t->subscription->fresh()->status);
    }

    public function test_resilier_ferme_l_acces(): void
    {
        $t = $this->tenant();
        $this->subscriptions()->cancel($t);

        $this->assertSame('cancelled', $t->subscription->fresh()->status);
        $this->assertSame('suspended', $t->fresh()->status);
    }

    // ---------- Console : paiements ----------

    public function test_valider_et_refuser_depuis_la_console(): void
    {
        $t = $this->tenant();
        $ok = $this->subscriptions()->declarePayment($t, ['plan_id' => $this->plan('petite')->id, 'amount' => 10000, 'method' => 'cash']);
        $ko = $this->subscriptions()->declarePayment($t, ['amount' => 5, 'method' => 'cash']);
        $this->actingAs($this->admin('admin'), 'admin');

        $this->get('/console/paiements')->assertOk()->assertSee('À valider (2)');
        $this->post("/console/paiements/{$ok->id}/valider")->assertRedirect();
        $this->post("/console/paiements/{$ko->id}/refuser", ['note' => ''])->assertSessionHasErrors('note');
        $this->post("/console/paiements/{$ko->id}/refuser", ['note' => 'Montant insuffisant'])->assertRedirect();

        $this->assertSame('validated', $ok->fresh()->status);
        $this->assertSame('rejected', $ko->fresh()->status);
        $this->assertSame('Montant insuffisant', $ko->fresh()->note);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.validate']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.reject']);
    }

    public function test_encaissement_hors_ligne_depuis_la_fiche(): void
    {
        $t = $this->tenant();
        $this->actingAs($this->admin('admin'), 'admin');

        $this->post('/console/entreprises/acme/abonnement/paiement', [
            'plan_id' => $this->plan('standard')->id, 'months' => 3, 'amount' => 75000, 'method' => 'mobile_money', 'reference' => 'MM-42',
        ])->assertRedirect();

        $p = SubscriptionPayment::firstWhere('tenant_id', 'acme');
        $this->assertSame('validated', $p->status);
        $this->assertSame('console', $p->source);
        $this->assertSame('active', $t->subscription->fresh()->status);
        $this->assertEqualsWithDelta(90, $t->subscription->fresh()->daysLeft(), 3);
    }

    public function test_actions_d_abonnement_reservees_aux_roles_autorises(): void
    {
        $this->tenant();
        $this->actingAs($this->admin('support'), 'admin');

        $this->post('/console/entreprises/acme/abonnement/activer', ['plan_id' => 2, 'months' => 1])->assertForbidden();
        $this->post('/console/entreprises/acme/abonnement/jours-offerts', ['days' => 5])->assertForbidden();
        $this->post('/console/entreprises/acme/abonnement/paiement', ['plan_id' => 2, 'months' => 1, 'amount' => 1, 'method' => 'cash'])->assertForbidden();
    }

    public function test_resiliation_demande_la_confirmation(): void
    {
        $this->tenant();
        $this->actingAs($this->admin('admin'), 'admin');

        $this->post('/console/entreprises/acme/abonnement/resilier', ['confirm' => 'faux'])->assertSessionHasErrors('confirm');
        $this->post('/console/entreprises/acme/abonnement/resilier', ['confirm' => 'acme'])->assertRedirect();
        $this->assertSame('cancelled', Subscription::firstWhere('tenant_id', 'acme')->status);
    }

    // ---------- Limites ----------

    public function test_limites_du_forfait_et_personnalisees(): void
    {
        $t = $this->tenant();
        $limits = app(PlanLimits::class);

        $this->assertSame(['users' => 3, 'products' => 100, 'invoices_month' => 50, 'branches' => null], $limits->limitsFor($t), 'forfait d\'essai');

        $t->subscription->update(['custom_limits' => ['users' => 20, 'products' => null]]);
        $this->assertSame(['users' => 20, 'products' => null, 'invoices_month' => 50, 'branches' => null], $limits->limitsFor($t->fresh()));
    }

    public function test_paliers_d_usage(): void
    {
        $this->assertNull(PlanLimits::tier(null, 999999), 'illimité');
        $this->assertSame('normal', PlanLimits::tier(100, 79));
        $this->assertSame('warning', PlanLimits::tier(100, 80));
        $this->assertSame('critical', PlanLimits::tier(100, 95));
        $this->assertSame('reached', PlanLimits::tier(100, 100));
        $this->assertSame('reached', PlanLimits::tier(100, 150));
        $this->assertSame('reached', PlanLimits::tier(0, 0));
    }

    public function test_la_console_modifie_et_retire_les_limites_personnalisees(): void
    {
        $t = $this->tenant();
        $this->actingAs($this->admin('admin'), 'admin');

        $this->put('/console/limites/acme', ['users' => 12, 'unlimited' => ['products']])->assertRedirect();
        $this->assertSame(['users' => 12, 'products' => null], $t->subscription->fresh()->custom_limits);

        $this->put('/console/limites/acme', [])->assertRedirect();
        $this->assertNull($t->subscription->fresh()->custom_limits);
    }

    public function test_deblocage_temporaire_des_limites(): void
    {
        $t = $this->tenant();
        $this->actingAs($this->admin('admin'), 'admin');

        $this->post('/console/limites/acme/debloquer', ['days' => 7])->assertRedirect();
        $this->assertTrue($t->subscription->fresh()->limitsUnlocked());

        $this->post('/console/limites/acme/rebloquer')->assertRedirect();
        $this->assertFalse($t->subscription->fresh()->limitsUnlocked());
    }

    // ---------- API des entreprises ----------

    private function through(Tenant $tenant, string $path): \Symfony\Component\HttpFoundation\Response
    {
        app()->instance(\Stancl\Tenancy\Contracts\Tenant::class, $tenant);

        return (new EnsureTenantIsActive)->handle(Request::create($path, 'GET'), fn () => response('passé'));
    }

    public function test_une_entreprise_dont_l_abonnement_est_termine_peut_encore_payer(): void
    {
        $t = $this->tenant('acme', ['status' => 'suspended', 'suspension_source' => 'subscription', 'suspended_reason' => 'Essai gratuit terminé']);

        foreach (['/api/login', '/api/me', '/api/logout', '/api/password', '/api/subscription', '/api/subscription/payments'] as $path) {
            $this->assertSame(200, $this->through($t, $path)->getStatusCode(), $path);
        }

        foreach (['/api/products', '/api/documents', '/api/pos/sales', '/api/customers'] as $path) {
            $r = $this->through($t, $path);
            $this->assertSame(403, $r->getStatusCode(), $path);
            $this->assertSame('subscription_expired', json_decode($r->getContent(), true)['code']);
        }
    }

    public function test_une_suspension_decidee_par_la_console_bloque_tout_meme_le_paiement(): void
    {
        $t = $this->tenant('acme', ['status' => 'suspended', 'suspension_source' => 'console']);

        $r = $this->through($t, '/api/subscription');

        $this->assertSame(403, $r->getStatusCode());
        $this->assertSame('tenant_suspended', json_decode($r->getContent(), true)['code']);
    }

    public function test_les_routes_d_abonnement_et_les_limites_sont_branchees(): void
    {
        $routes = collect(app('router')->getRoutes());
        $get = fn (string $uri, string $method) => $routes->first(fn ($r) => $r->uri() === $uri && in_array($method, $r->methods()));

        $this->assertContains('permission:billing.manage', $get('api/subscription', 'GET')->gatherMiddleware());
        $this->assertContains('permission:billing.manage', $get('api/subscription/payments', 'POST')->gatherMiddleware());
        $this->assertContains('maintenance', $get('api/login', 'POST')->gatherMiddleware());
    }

    public function test_maintenance_renvoie_503_sur_l_api_mais_pas_sur_la_console(): void
    {
        PlatformSetting::put('maintenance', true);
        PlatformSetting::put('maintenance_message', 'Retour à 14 h.');

        $this->postJson('/api/login', ['email' => 'a@b.co', 'password' => 'x'], ['X-Tenant' => 'acme'])
            ->assertStatus(503)->assertJsonPath('code', 'maintenance')->assertJsonPath('message', 'Retour à 14 h.');

        $this->get('/console/login')->assertOk();
    }

    public function test_reglages_de_la_plateforme(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin');

        $this->put('/console/parametres', [
            'trial_days' => 21, 'grace_days' => 3, 'currency' => 'xaf', 'payment_instructions' => 'Wave 77 000 00 00',
            'deletion_grace_days' => 45, 'abandoned_days' => 120,
        ])->assertRedirect();

        $this->assertSame(21, PlatformSetting::int('trial_days'));
        $this->assertSame('XAF', PlatformSetting::get('currency'));
        $this->assertFalse(PlatformSetting::bool('signup_open'), 'case décochée = inscriptions fermées');

        $this->flushSession();
        $this->actingAs($this->admin('admin'), 'admin')->get('/console/parametres')->assertForbidden();
    }

    public function test_la_fiche_entreprise_affiche_l_abonnement(): void
    {
        $this->tenant();
        $this->actingAs($this->admin('admin'), 'admin');

        $this->get('/console/entreprises/acme')->assertOk()->assertSee('Abonnement')->assertSee('Essai gratuit')
            ->assertSee('Encaisser un paiement hors ligne')->assertSee('Offrir des jours');
    }

    public function test_les_pages_de_la_phase_2_s_affichent(): void
    {
        $this->tenant();
        $this->subscriptions()->declarePayment(Tenant::find('acme'), ['amount' => 100, 'method' => 'cash']);
        $this->actingAs($this->admin('super_admin'), 'admin');

        foreach (['/console/forfaits', '/console/forfaits/nouveau', '/console/echeances', '/console/echeances?filter=overdue',
            '/console/paiements', '/console/parametres'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
