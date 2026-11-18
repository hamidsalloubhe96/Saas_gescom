<?php

namespace Tests\Feature;

use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SupportController;
use App\Mail\SubscriptionReminder;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPayment;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Phase 5 : support, reçus, relances, webhook signé, version. */
class ConsolePhase5Test extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super_admin'): PlatformAdmin
    {
        $a = PlatformAdmin::create(['name' => ucfirst($role), 'email' => "$role@test.local", 'password' => 'Password-12345', 'role' => $role, 'is_active' => true]);
        $a->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $a->fresh();
    }

    private function tenant(string $id = 'acme'): Tenant
    {
        $t = Tenant::withoutEvents(fn () => Tenant::create(['id' => $id, 'name' => ucfirst($id), 'status' => 'active', 'owner_email' => "owner@$id.test"]));
        app(SubscriptionService::class)->startTrial($t);

        return $t->fresh();
    }

    /** Simule une requête d'un utilisateur de l'entreprise donnée (sans schéma réel). */
    private function asTenant(Tenant $t, string $method, array $data = []): Request
    {
        app()->instance(\Stancl\Tenancy\Contracts\Tenant::class, $t);
        $user = new \App\Models\User(['name' => 'Awa', 'email' => "awa@{$t->id}.test"]);
        $r = Request::create('/x', $method, $data);
        $r->setUserResolver(fn () => $user);

        return $r;
    }

    private function pending(Tenant $t, array $over = []): SubscriptionPayment
    {
        $plan = Plan::firstWhere('slug', 'standard');

        return app(SubscriptionService::class)->declarePayment($t, $over + ['plan_id' => $plan->id, 'amount' => $plan->price, 'method' => 'mobile_money', 'reference' => 'TX-1001']);
    }

    // ---------- Support ----------

    public function test_un_ticket_est_cree_et_limite_a_cinq_en_cours(): void
    {
        $t = $this->tenant();
        $c = new SupportController;

        $res = $c->store($this->asTenant($t, 'POST', ['subject' => 'Problème de caisse', 'body' => 'La caisse ne s\'ouvre plus.']));
        $this->assertSame(201, $res->getStatusCode());
        $this->assertSame(1, SupportTicket::first()->messages()->count());

        for ($i = 0; $i < 4; $i++) {
            $c->store($this->asTenant($t, 'POST', ['subject' => "Autre $i", 'body' => 'Un message de test.']));
        }
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $c->store($this->asTenant($t, 'POST', ['subject' => 'Sixième', 'body' => 'Un message de test.']));
    }

    public function test_un_ticket_n_est_visible_que_par_son_entreprise(): void
    {
        $a = $this->tenant('acme');
        $b = $this->tenant('beta');
        $c = new SupportController;
        $id = $c->store($this->asTenant($a, 'POST', ['subject' => 'Secret', 'body' => 'Contenu privé de acme.']))->getData()->id;

        $this->assertSame(200, $c->show($id)->getStatusCode(), 'dans le contexte de acme');
        $this->asTenant($b, 'GET');
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $c->show($id);
    }

    public function test_repondre_rouvre_le_ticket_et_un_ticket_clos_refuse(): void
    {
        $t = $this->tenant();
        $c = new SupportController;
        $id = $c->store($this->asTenant($t, 'POST', ['subject' => 'Aide', 'body' => 'Besoin d\'aide svp.']))->getData()->id;
        SupportTicket::find($id)->update(['status' => 'answered']);

        $c->reply($this->asTenant($t, 'POST', ['body' => 'Merci, ça marche.']), $id);
        $this->assertSame('open', SupportTicket::find($id)->status);

        SupportTicket::find($id)->update(['status' => 'closed']);
        try {
            $c->reply($this->asTenant($t, 'POST', ['body' => 'Encore une question']), $id);
            $this->fail();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_la_console_repond_et_cloture_un_ticket(): void
    {
        $t = $this->tenant();
        $ticket = SupportTicket::create(['tenant_id' => 'acme', 'author_name' => 'Awa', 'subject' => 'Aide', 'status' => 'open', 'last_message_at' => now()]);
        $ticket->messages()->create(['author_type' => 'tenant', 'author_name' => 'Awa', 'body' => 'Bonjour']);
        $this->actingAs($this->admin('support'), 'admin');

        $this->get('/console/support')->assertOk()->assertSee('Aide');
        $this->get("/console/support/{$ticket->id}")->assertOk()->assertSee('Bonjour');
        $this->post("/console/support/{$ticket->id}/repondre", ['body' => 'Voici la solution.'])->assertRedirect();
        $this->assertSame('answered', $ticket->fresh()->status);
        $this->assertSame(2, $ticket->messages()->count());

        $this->patch("/console/support/{$ticket->id}/statut", ['status' => 'closed'])->assertRedirect();
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->get('/console/support')->assertDontSee('Aide');
        $this->get('/console/support?status=closed')->assertSee('Aide');
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.reply', 'tenant_id' => 'acme']);
    }

    public function test_le_support_exige_la_permission_tickets(): void
    {
        $this->tenant();
        $ticket = SupportTicket::create(['tenant_id' => 'acme', 'author_name' => 'Awa', 'subject' => 'Aide', 'status' => 'open', 'last_message_at' => now()]);
        $this->get('/console/support')->assertRedirect();

        $this->actingAs($this->admin('support'), 'admin')->get('/console/support')->assertOk();
        $this->post("/console/support/{$ticket->id}/repondre", ['body' => ''])->assertSessionHasErrors('body');
    }

    // ---------- Reçus ----------

    public function test_un_recu_n_existe_que_pour_un_paiement_valide(): void
    {
        $t = $this->tenant();
        $p = $this->pending($t);

        try {
            $p->receipt();
            $this->fail();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }

        $p = app(SubscriptionService::class)->validatePayment($p, $this->admin());
        $r = $p->receipt();
        $this->assertMatchesRegularExpression('/^REC-\d{4}-\d{6}$/', $r['number']);
        $this->assertSame('Acme', $r['company']);
        $this->assertSame((float) $p->amount, $r['amount']);
    }

    public function test_recu_cote_console_et_cote_entreprise(): void
    {
        $a = $this->tenant('acme');
        $b = $this->tenant('beta');
        $admin = $this->admin();
        $p = app(SubscriptionService::class)->validatePayment($this->pending($a), $admin);

        $this->actingAs($admin, 'admin')->get("/console/paiements/{$p->id}/recu")->assertOk()->assertSee($p->receiptNumber())->assertSee('Acme');

        $c = new SubscriptionController;
        $this->asTenant($a, 'GET');
        $this->assertSame($p->receiptNumber(), $c->receipt($p->id)->getData()->number);
        $this->asTenant($b, 'GET');
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $c->receipt($p->id);
    }

    // ---------- Relances ----------

    public function test_relance_d_echeance_envoyee_une_seule_fois(): void
    {
        Mail::fake();
        $t = $this->tenant();
        app(SubscriptionService::class)->activate($t, Plan::firstWhere('slug', 'standard'));
        $t->subscription->update(['current_period_end' => now()->addDays(2)]);

        $this->artisan('console:extra-tasks')->assertSuccessful();
        $this->artisan('console:extra-tasks')->assertSuccessful();

        Mail::assertSent(SubscriptionReminder::class, 1);
        Mail::assertSent(SubscriptionReminder::class, fn ($m) => $m->hasTo('owner@acme.test') && $m->kind === 'expiring');
    }

    public function test_pas_de_relance_loin_de_l_echeance_ni_pour_un_abonnement_ancien(): void
    {
        Mail::fake();
        $far = $this->tenant('loin');
        app(SubscriptionService::class)->activate($far, Plan::firstWhere('slug', 'standard'));
        $far->subscription->update(['current_period_end' => now()->addDays(20)]);

        $old = $this->tenant('ancien');
        $old->subscription->update(['status' => 'expired', 'trial_ends_at' => now()->subDays(90), 'current_period_end' => now()->subDays(90)]);

        $this->artisan('console:extra-tasks')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_relance_apres_echeance(): void
    {
        Mail::fake();
        $t = $this->tenant();
        $t->subscription->update(['status' => 'past_due', 'current_period_end' => now()->subDays(2), 'trial_ends_at' => now()->subDays(2)]);

        $this->artisan('console:extra-tasks')->assertSuccessful();

        Mail::assertSent(SubscriptionReminder::class, fn ($m) => $m->kind === 'overdue');
    }

    // ---------- Webhook ----------

    private function webhook(array $payload, ?string $secret = 'topsecret', ?string $signWith = null)
    {
        $body = json_encode($payload);
        $sig = hash_hmac('sha256', $body, $signWith ?? ($secret ?? 'x'));

        return $this->call('POST', '/api/webhooks/payments', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $sig, 'HTTP_ACCEPT' => 'application/json'], $body);
    }

    public function test_le_webhook_est_ferme_sans_secret(): void
    {
        config(['services.payment_webhook.secret' => null]);

        $this->webhook(['reference' => 'TX-1001', 'amount' => 25000, 'status' => 'paid'])->assertNotFound();
    }

    public function test_le_webhook_refuse_une_mauvaise_signature(): void
    {
        config(['services.payment_webhook.secret' => 'topsecret']);
        $p = $this->pending($this->tenant());

        $this->webhook(['reference' => 'TX-1001', 'amount' => 25000, 'status' => 'paid'], signWith: 'autre')->assertStatus(401);
        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_le_webhook_valide_un_paiement_declare(): void
    {
        config(['services.payment_webhook.secret' => 'topsecret']);
        $t = $this->tenant();
        $p = $this->pending($t);

        $this->webhook(['reference' => 'TX-1001', 'amount' => (float) $p->amount, 'status' => 'paid'])->assertOk();

        $this->assertSame('validated', $p->fresh()->status);
        $this->assertSame('webhook', $p->fresh()->source);
        $this->assertSame('active', $t->fresh()->subscription->status);

        // Rejoué : plus rien en attente, aucune double prolongation.
        $end = $t->fresh()->subscription->current_period_end;
        $this->webhook(['reference' => 'TX-1001', 'amount' => (float) $p->amount, 'status' => 'paid'])->assertStatus(202);
        $this->assertEquals($end, $t->fresh()->subscription->current_period_end);
    }

    public function test_le_webhook_laisse_en_attente_un_montant_insuffisant_et_refuse_un_echec(): void
    {
        config(['services.payment_webhook.secret' => 'topsecret']);
        $t = $this->tenant();
        $p = $this->pending($t);

        $this->webhook(['reference' => 'TX-1001', 'amount' => 1, 'status' => 'paid'])->assertStatus(202);
        $this->assertSame('pending', $p->fresh()->status);

        $this->webhook(['reference' => 'TX-1001', 'amount' => 0, 'status' => 'failed'])->assertOk();
        $this->assertSame('rejected', $p->fresh()->status);

        $this->webhook(['reference' => 'INCONNUE', 'amount' => 5, 'status' => 'paid'])->assertStatus(202);
    }

    // ---------- Version ----------

    public function test_la_version_est_publique_et_courte(): void
    {
        config(['app.version' => 'abcdef1234567890abcdef']);

        $this->getJson('/api/public/version')->assertOk()->assertExactJson(['version' => 'abcdef123456']);
    }
}
