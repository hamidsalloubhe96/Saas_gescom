<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailCode;
use App\Models\AuditLog;
use App\Models\LegalText;
use App\Models\MaintenanceNote;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\TaskRun;
use App\Models\Tenant;
use App\Services\EmailVerification;
use App\Services\ErrorAlert;
use App\Services\SubscriptionService;
use App\Services\TaskRunner;
use App\Services\TenantExporter;
use App\Services\TenantLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase 4 : santé, textes légaux, fin de vie des entreprises, exports, durcissement de l'inscription. */
class ConsolePhase4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['console.backup_disk' => 'local']);
        $c = config('tenancy.database.central_connection');
        config(["database.connections.$c.host" => 'localhost', "database.connections.$c.username" => 'app', "database.connections.$c.database" => 'gestcom']);
    }

    private function admin(string $role = 'super_admin'): PlatformAdmin
    {
        $a = PlatformAdmin::create(['name' => ucfirst($role), 'email' => "$role@test.local", 'password' => 'Password-12345', 'role' => $role, 'is_active' => true]);
        $a->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $a->fresh();
    }

    private function tenant(string $id = 'acme', array $extra = []): Tenant
    {
        $t = Tenant::withoutEvents(fn () => Tenant::create($extra + ['id' => $id, 'name' => ucfirst($id), 'status' => 'active']));
        app(SubscriptionService::class)->startTrial($t);

        return $t->fresh();
    }

    /** Simule pg_dump : écrit un contenu dans le fichier demandé (--file) et réussit, ou échoue. */
    private function fakePgDump(bool $succeed = true): void
    {
        Process::fake(function ($process) use ($succeed) {
            $cmd = is_array($process->command) ? $process->command : [];
            if (! $succeed) {
                return Process::result(output: '', errorOutput: 'pg_dump: erreur de connexion simulée', exitCode: 1);
            }
            $i = array_search('--file', $cmd, true);
            if ($i !== false) {
                file_put_contents($cmd[$i + 1], 'PGDMP-contenu-simule');
            }

            return Process::result(output: 'ok');
        });
    }

    // ---------- Suppression programmée ----------

    public function test_programmer_la_suppression_prend_un_export_et_ferme_l_entreprise(): void
    {
        $this->fakePgDump();
        PlatformSetting::put('deletion_grace_days', 30);
        $t = $this->tenant();

        $date = app(TenantLifecycle::class)->schedule($t, 'Demande du client');

        $t->refresh();
        $this->assertSame('pending_deletion', $t->status);
        $this->assertEqualsWithDelta(30, now()->diffInDays($date), 1);
        $this->assertTrue($t->deletion_scheduled_at->isFuture());
        $this->assertCount(1, Storage::disk('local')->files('exports/acme'), 'un export est pris AVANT toute programmation');
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.schedule_deletion', 'tenant_id' => 'acme']);
    }

    public function test_sans_export_reussi_rien_n_est_programme(): void
    {
        $this->fakePgDump(false);
        $t = $this->tenant();

        try {
            app(TenantLifecycle::class)->schedule($t, 'Test');
            $this->fail('Une exception était attendue');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Export impossible', $e->getMessage());
        }

        $t->refresh();
        $this->assertSame('active', $t->status, 'l\'entreprise n\'est pas fermée si l\'export a échoué');
        $this->assertNull($t->deletion_scheduled_at);
    }

    public function test_une_entreprise_protegee_ou_deja_programmee_est_refusee(): void
    {
        $this->fakePgDump();
        $lifecycle = app(TenantLifecycle::class);

        $p = $this->tenant('prot', ['is_protected' => true]);
        try {
            $lifecycle->schedule($p, 'x');
            $this->fail();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $t = $this->tenant('deja');
        $lifecycle->schedule($t, 'x');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $lifecycle->schedule($t->fresh(), 'encore');
    }

    public function test_annuler_la_suppression_rouvre_l_acces(): void
    {
        $this->fakePgDump();
        $t = $this->tenant();
        $lifecycle = app(TenantLifecycle::class);
        $lifecycle->schedule($t, 'Erreur');

        $lifecycle->cancel($t->fresh());

        $t->refresh();
        $this->assertSame('active', $t->status);
        $this->assertNull($t->deletion_scheduled_at);
        $this->assertNull($t->suspended_reason);
    }

    public function test_proteger_annule_une_suppression_programmee(): void
    {
        $this->fakePgDump();
        $t = $this->tenant();
        app(TenantLifecycle::class)->schedule($t, 'x', block: false);

        app(TenantLifecycle::class)->setProtected($t->fresh(), true);

        $this->assertNull($t->fresh()->deletion_scheduled_at);
        $this->assertTrue($t->fresh()->is_protected);
    }

    public function test_la_purge_ne_supprime_que_les_echues_non_protegees_et_non_actives(): void
    {
        $past = now()->subDay();
        $this->tenant('echue', ['status' => 'pending_deletion', 'deletion_scheduled_at' => $past]);
        $this->tenant('future', ['status' => 'pending_deletion', 'deletion_scheduled_at' => now()->addDays(5)]);
        $this->tenant('protegee', ['status' => 'pending_deletion', 'deletion_scheduled_at' => $past, 'is_protected' => true]);
        $this->tenant('reactivee', ['status' => 'active', 'deletion_scheduled_at' => $past]);

        // Sans événements : on ne supprime pas de vrai schéma PostgreSQL dans un test.
        $r = Tenant::withoutEvents(fn () => app(TenantLifecycle::class)->purgeDue());

        $this->assertSame(1, $r['purged']);
        $this->assertNull(Tenant::find('echue'));
        foreach (['future', 'protegee', 'reactivee'] as $id) {
            $this->assertNotNull(Tenant::find($id), "{$id} doit être conservée");
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.purged', 'tenant_id' => 'echue']);
    }

    public function test_entreprises_abandonnees_programmees_sauf_protegees_et_actives(): void
    {
        $this->fakePgDump();
        PlatformSetting::put('abandoned_days', 90);

        $mk = function (string $id, array $extra = [], string $subStatus = 'expired') {
            $t = $this->tenant($id, $extra + ['status' => 'suspended', 'suspension_source' => 'subscription', 'last_activity_at' => now()->subDays(200)]);
            $t->subscription->update(['status' => $subStatus]);

            return $t;
        };
        $mk('abandonnee');
        $mk('recente', ['last_activity_at' => now()->subDays(10)]);
        $mk('protegee', ['is_protected' => true]);
        $mk('en_essai', [], 'trial');
        $this->tenant('active_ancienne', ['last_activity_at' => now()->subDays(300)]);

        $r = app(TenantLifecycle::class)->flagAbandoned();

        $this->assertSame(1, $r['flagged']);
        $this->assertNotNull(Tenant::find('abandonnee')->deletion_scheduled_at);
        $this->assertSame('suspended', Tenant::find('abandonnee')->status, 'elle garde son statut : elle peut encore payer');
        foreach (['recente', 'protegee', 'en_essai', 'active_ancienne'] as $id) {
            $this->assertNull(Tenant::find($id)->deletion_scheduled_at, $id);
        }
    }

    public function test_un_paiement_annule_la_suppression_automatique(): void
    {
        $this->fakePgDump();
        $t = $this->tenant('abandonnee', ['status' => 'suspended', 'suspension_source' => 'subscription', 'last_activity_at' => now()->subDays(200)]);
        $t->subscription->update(['status' => 'expired']);
        app(TenantLifecycle::class)->flagAbandoned();
        $this->assertNotNull($t->fresh()->deletion_scheduled_at);

        app(SubscriptionService::class)->activate($t->fresh(), \App\Models\Plan::firstWhere('slug', 'standard'));

        $this->assertNull($t->fresh()->deletion_scheduled_at);
        $this->assertSame('active', $t->fresh()->status);
    }

    public function test_l_api_d_une_entreprise_en_suppression_programmee_est_fermee(): void
    {
        $t = $this->tenant('acme', ['status' => 'pending_deletion', 'deletion_scheduled_at' => now()->addDays(10)]);
        app()->instance(\Stancl\Tenancy\Contracts\Tenant::class, $t);

        $r = (new \App\Http\Middleware\EnsureTenantIsActive)->handle(\Illuminate\Http\Request::create('/api/me', 'GET'), fn () => response('passé'));

        $this->assertSame(403, $r->getStatusCode());
        $this->assertSame('tenant_pending_deletion', json_decode($r->getContent(), true)['code']);
    }

    public function test_la_fin_de_vie_est_reservee_au_super_admin(): void
    {
        $this->fakePgDump();
        $this->tenant();
        $this->actingAs($this->admin('admin'), 'admin');

        $this->post('/console/entreprises/acme/suppression', ['reason' => 'x', 'confirm' => 'acme'])->assertForbidden();
        $this->post('/console/entreprises/acme/protection', ['protected' => 1])->assertForbidden();
        $this->post('/console/entreprises/acme/export')->assertForbidden();
    }

    public function test_programmer_depuis_la_console_exige_la_confirmation(): void
    {
        $this->fakePgDump();
        $this->tenant();
        $this->actingAs($this->admin(), 'admin');

        $this->post('/console/entreprises/acme/suppression', ['reason' => 'Fraude', 'confirm' => 'faux'])->assertSessionHasErrors('confirm');
        $this->assertSame('active', Tenant::find('acme')->status);

        $this->post('/console/entreprises/acme/suppression', ['reason' => 'Fraude', 'confirm' => 'acme'])->assertRedirect();
        $this->assertSame('pending_deletion', Tenant::find('acme')->status);
    }

    // ---------- Exports ----------

    public function test_le_mot_de_passe_ne_passe_jamais_par_la_ligne_de_commande(): void
    {
        $t = $this->tenant();
        $c = config('tenancy.database.central_connection');
        config(["database.connections.$c.url" => 'postgresql://app:s3cr%40t@db.example.com:5432/gestcom?sslmode=require']);
        $seen = [];
        Process::fake(function ($process) use (&$seen) {
            $seen = ['cmd' => $process->command, 'env' => $process->environment];
            $cmd = $process->command;
            file_put_contents($cmd[array_search('--file', $cmd, true) + 1], 'x');

            return Process::result(output: 'ok');
        });

        app(TenantExporter::class)->export($t);

        $this->assertSame('s3cr@t', $seen['env']['PGPASSWORD'], 'mot de passe décodé, transmis par l\'environnement');
        $this->assertSame('require', $seen['env']['PGSSLMODE']);
        $this->assertNotContains('s3cr@t', $seen['cmd']);
        $this->assertStringNotContainsString('s3cr', implode(' ', $seen['cmd']));
        $this->assertContains('db.example.com', $seen['cmd']);
        $this->assertContains('tenantacme', $seen['cmd'], 'schéma de l\'entreprise');
    }

    public function test_telechargement_limite_aux_dossiers_d_export(): void
    {
        Storage::disk('local')->put('exports/acme/acme-1.dump', 'x');
        Storage::disk('local')->put('secret.txt', 'x');
        $this->actingAs($this->admin(), 'admin');

        $this->get('/console/sante/telecharger?path='.urlencode('exports/acme/acme-1.dump'))->assertOk();
        $this->get('/console/sante/telecharger?path='.urlencode('../.env'))->assertNotFound();
        $this->get('/console/sante/telecharger?path='.urlencode('exports/../secret.txt'))->assertNotFound();
        $this->get('/console/sante/telecharger?path='.urlencode('secret.txt'))->assertNotFound();
        $this->get('/console/sante/telecharger?path='.urlencode('exports/acme/acme-1.txt'))->assertNotFound();
    }

    // ---------- Tâches, santé, alertes ----------

    public function test_une_tache_qui_echoue_n_empeche_pas_les_suivantes(): void
    {
        $runner = app(TaskRunner::class);
        $order = [];

        $a = $runner->run('premiere', function () use (&$order) { $order[] = 'a'; throw new \RuntimeException('boum'); });
        $b = $runner->run('seconde', function () use (&$order) { $order[] = 'b'; return 'fait'; });

        $this->assertSame(['a', 'b'], $order);
        $this->assertSame('failed', $a->status);
        $this->assertSame('boum', $a->message);
        $this->assertSame('ok', $b->status);
        $this->assertSame(2, TaskRun::count());
    }

    public function test_la_commande_quotidienne_enregistre_chaque_tache(): void
    {
        $this->artisan('console:daily')->assertSuccessful();

        $names = TaskRun::pluck('name')->all();
        foreach (['abonnements', 'entreprises-abandonnees', 'purge-entreprises', 'nettoyage-journal', 'taches-complementaires'] as $n) {
            $this->assertContains($n, $names);
        }
        $this->assertSame(0, TaskRun::where('status', 'failed')->count());
    }

    public function test_le_nettoyage_du_journal_respecte_la_retention(): void
    {
        $old = AuditLog::create(['action' => 'x', 'description' => 'vieux', 'created_at' => now()->subDays(400)]);
        $new = AuditLog::create(['action' => 'x', 'description' => 'récent', 'created_at' => now()->subDays(10)]);

        $this->artisan('console:daily')->assertSuccessful();

        $this->assertNull(AuditLog::find($old->id));
        $this->assertNotNull(AuditLog::find($new->id));
    }

    public function test_alerte_d_erreur_limitee_et_desactivable(): void
    {
        Mail::fake();
        $alert = app(ErrorAlert::class);
        $e = new \RuntimeException('Base injoignable');

        config(['console.alert_email' => null]);
        $this->assertFalse($alert->notify($e));
        Mail::assertNothingSent();

        config(['console.alert_email' => 'ops@test.local']);
        $this->assertTrue($alert->notify($e));
        $this->assertFalse($alert->notify($e), 'la même erreur n\'est pas renvoyée avant 10 minutes');
        $this->assertTrue($alert->notify($e, force: true), 'le test depuis la console force l\'envoi');
    }

    public function test_la_page_sante_s_affiche_et_est_reservee_au_super_admin(): void
    {
        $this->tenant();
        TaskRun::create(['name' => 'abonnements', 'status' => 'ok', 'message' => '0 en retard', 'duration_ms' => 12, 'ran_at' => now()]);

        $this->actingAs($this->admin(), 'admin')->get('/console/sante')->assertOk()
            ->assertSee('Santé de l\'application', false)->assertSee('Tester l\'envoi d\'e-mail', false)->assertSee('Noter comme fait', false)->assertSee('Migrations des entreprises', false);

        $this->flushSession();
        $this->actingAs($this->admin('admin'), 'admin')->get('/console/sante')->assertForbidden();
    }

    public function test_noter_un_rappel_comme_fait(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/console/sante/noter', ['key' => 'restore_test'])->assertRedirect();
        $this->assertNotNull(MaintenanceNote::find('restore_test')->done_at);
        $this->post('/console/sante/noter', ['key' => 'inconnu'])->assertSessionHasErrors('key');
    }

    public function test_une_tache_en_retard_est_signalee(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->get('/console/sante')->assertSee('n\'ont pas tourné depuis plus de 26 h', false);

        TaskRun::create(['name' => 'abonnements', 'status' => 'ok', 'duration_ms' => 1, 'ran_at' => now()]);
        $this->get('/console/sante')->assertDontSee('n\'ont pas tourné depuis plus de 26 h', false);
    }

    // ---------- Textes légaux ----------

    public function test_publier_un_texte_cree_des_versions_numerotees(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->get('/console/textes-legaux')->assertOk();

        $this->post('/console/textes-legaux/terms/publier', ['title' => 'CGU', 'body' => 'Article 1 : les conditions de base du service GestCom.'])->assertRedirect();
        $this->post('/console/textes-legaux/terms/publier', ['title' => 'CGU', 'body' => 'Article 1 : conditions révisées, version deux du texte.'])->assertRedirect();

        $this->assertSame(2, LegalText::current('terms')->version);
        $this->assertSame([1, 2], LegalText::where('key', 'terms')->where('status', 'published')->orderBy('version')->pluck('version')->all());
        $this->assertStringContainsString('révisées', LegalText::current('terms')->body);
    }

    public function test_un_texte_vide_ou_inconnu_ne_se_publie_pas(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/console/textes-legaux/terms/publier', ['title' => 'CGU', 'body' => ''])->assertSessionHasErrors('body');
        $this->post('/console/textes-legaux/terms/publier', ['title' => 'CGU', 'body' => 'court'])->assertSessionHasErrors('body');
        $this->post('/console/textes-legaux/piratage/publier', ['title' => 'X', 'body' => str_repeat('a', 30)])->assertNotFound();
        $this->assertNull(LegalText::current('terms'));
    }

    public function test_le_brouillon_n_est_pas_public_tant_qu_il_n_est_pas_publie(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->put('/console/textes-legaux/privacy', ['title' => 'Confidentialité', 'body' => 'Brouillon en cours de rédaction, pas encore validé.'])->assertRedirect();

        $this->getJson('/api/public/legal/privacy')->assertNotFound();

        $this->post('/console/textes-legaux/privacy/publier', ['title' => 'Confidentialité', 'body' => 'Version validée de la politique de confidentialité.'])->assertRedirect();
        $this->put('/console/textes-legaux/privacy', ['title' => 'Confidentialité', 'body' => 'Nouveau brouillon qui ne doit pas encore apparaître au public.'])->assertRedirect();

        $this->getJson('/api/public/legal/privacy')->assertOk()->assertJsonPath('version', 1)
            ->assertJsonFragment(['body' => 'Version validée de la politique de confidentialité.']);

        $this->post('/console/textes-legaux/privacy/reinitialiser')->assertRedirect();
        $this->assertSame('Version validée de la politique de confidentialité.', LegalText::draft('privacy')->body);
    }

    public function test_liste_publique_des_textes(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->post('/console/textes-legaux/terms/publier', ['title' => 'CGU', 'body' => 'Conditions générales publiées pour le test.'])->assertRedirect();

        $json = $this->getJson('/api/public/legal')->assertOk()->json();

        $this->assertCount(4, $json);
        $this->assertTrue(collect($json)->firstWhere('key', 'terms')['available']);
        $this->assertFalse(collect($json)->firstWhere('key', 'privacy')['available']);
        $this->getJson('/api/public/legal/inconnu')->assertNotFound();
    }

    public function test_les_textes_legaux_sont_reserves_au_super_admin(): void
    {
        $this->actingAs($this->admin('admin'), 'admin');
        $this->get('/console/textes-legaux')->assertForbidden();
        $this->post('/console/textes-legaux/terms/publier', ['title' => 'x', 'body' => str_repeat('a', 30)])->assertForbidden();
    }

    // ---------- Inscription : durcissement ----------

    public function test_les_adresses_jetables_sont_refusees_partout(): void
    {
        $this->postJson('/api/register-tenant', ['company' => 'X', 'slug' => 'xx_corp', 'name' => 'X', 'email' => 'bob@mailinator.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/signup-requests', ['name' => 'Bob', 'email' => 'bob@YOPMAIL.com', 'company' => 'X'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/register-tenant/code', ['email' => 'bob@guerrillamail.com'])->assertStatus(422);
        $this->assertTrue(\App\Support\DisposableEmails::is('x@Mailinator.COM'));
        $this->assertFalse(\App\Support\DisposableEmails::is('x@gmail.com'));
    }

    public function test_le_code_de_verification_est_envoye_puis_exige(): void
    {
        Mail::fake();
        PlatformSetting::put('email_verification', true);
        $payload = ['company' => 'Acme', 'slug' => 'acme_co', 'name' => 'Ali', 'email' => 'ali@acme.test', 'password' => 'password123', 'password_confirmation' => 'password123'];

        // Sans code : refusé, et rien n'est créé.
        $this->postJson('/api/register-tenant', $payload)->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson('/api/register-tenant', $payload + ['code' => '123456'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertSame(0, Tenant::count());

        $this->postJson('/api/register-tenant/code', ['email' => 'ali@acme.test'])->assertOk();
        Mail::assertSent(VerifyEmailCode::class, fn ($m) => $m->hasTo('ali@acme.test') && preg_match('/^\d{6}$/', $m->code));
    }

    public function test_un_bon_code_ouvre_la_creation_et_ne_sert_qu_une_fois(): void
    {
        Mail::fake();
        PlatformSetting::put('email_verification', true);
        $sentCode = null;
        $this->postJson('/api/register-tenant/code', ['email' => 'ali@acme.test'])->assertOk();
        Mail::assertSent(VerifyEmailCode::class, function ($m) use (&$sentCode) { $sentCode = $m->code; return true; });

        $acme = $this->tenant('acme_co');
        $this->mock(\App\Services\TenantProvisioner::class, fn ($mock) => $mock->shouldReceive('provision')->once()->andReturn(['tenant' => $acme, 'user' => null, 'token' => 'tok']));

        $payload = ['company' => 'Acme', 'slug' => 'acme_co2', 'name' => 'Ali', 'email' => 'ali@acme.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'code' => $sentCode];
        $this->postJson('/api/register-tenant', $payload)->assertCreated()->assertJsonPath('token', 'tok');
        $this->postJson('/api/register-tenant', $payload)->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_le_code_est_stocke_hache_et_limite_a_5_essais(): void
    {
        Mail::fake();
        $v = app(EmailVerification::class);
        $sent = null;
        $v->send('ali@acme.test');
        Mail::assertSent(VerifyEmailCode::class, function ($m) use (&$sent) { $sent = $m->code; return true; });

        $cached = \Illuminate\Support\Facades\Cache::get('email_code:'.sha1('ali@acme.test'));
        $this->assertStringNotContainsString($sent, json_encode($cached), 'le code en clair ne doit pas être dans le cache');

        $wrong = $sent === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($v->check('ali@acme.test', $wrong));
        }
        $this->assertFalse($v->check('ali@acme.test', $sent), 'après 5 essais ratés, même le bon code est refusé');
    }

    public function test_les_reglages_publics_exposent_la_verification(): void
    {
        PlatformSetting::put('email_verification', true);

        $this->getJson('/api/public/config')->assertOk()->assertJsonPath('email_verification', true);
    }
}
