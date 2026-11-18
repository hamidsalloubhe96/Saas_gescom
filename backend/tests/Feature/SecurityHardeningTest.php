<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveClientIp;
use App\Models\PlatformAdmin;
use App\Services\LoginAttempts;
use App\Services\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Protection contre les robots : IP réelle derrière les intermédiaires, blocage progressif, anti-robot optionnel. */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function ipFor(?string $forwarded, int $hops, string $remote = '10.0.0.1'): string
    {
        config(['app.trusted_proxy_hops' => $hops]);
        $request = Request::create('/x', 'GET', server: array_filter(['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_FOR' => $forwarded]));
        (new ResolveClientIp)->handle($request, fn ($r) => response('ok'));

        return $request->ip();
    }

    public function test_sans_intermediaire_l_adresse_directe_est_conservee(): void
    {
        $this->assertSame('10.0.0.1', $this->ipFor(null, 0));
        $this->assertSame('10.0.0.1', $this->ipFor('8.8.8.8', 0), 'en local, un en-tête X-Forwarded-For est ignoré');
    }

    public function test_derriere_render_on_lit_la_derniere_entree(): void
    {
        $this->assertSame('203.0.113.7', $this->ipFor('203.0.113.7', 1));
        // Un visiteur qui falsifie l'en-tête ne peut agir que sur la gauche : Render ajoute la vraie adresse à la fin.
        $this->assertSame('203.0.113.7', $this->ipFor('1.1.1.1, 203.0.113.7', 1));
    }

    public function test_derriere_cloudflare_puis_render_on_remonte_d_un_cran(): void
    {
        $this->assertSame('203.0.113.7', $this->ipFor('203.0.113.7, 172.70.1.1', 2));
        $this->assertSame('203.0.113.7', $this->ipFor('9.9.9.9, 203.0.113.7, 172.70.1.1', 2), 'la falsification à gauche est sans effet');
    }

    public function test_une_valeur_invalide_est_ignoree(): void
    {
        $this->assertSame('10.0.0.1', $this->ipFor('pas-une-ip', 1));
    }

    public function test_le_blocage_est_progressif_et_temporaire(): void
    {
        $attempts = app(LoginAttempts::class);
        $key = LoginAttempts::key('t:acme', 'Ali@Acme.test');

        for ($i = 0; $i < LoginAttempts::FREE_ATTEMPTS - 1; $i++) {
            $attempts->fail($key);
            $this->assertSame(0, $attempts->waitSeconds($key), 'les premiers échecs sont libres');
        }

        $attempts->fail($key); // 5e échec
        $this->assertEqualsWithDelta(30, $attempts->waitSeconds($key), 2);
        $attempts->fail($key); // 6e
        $this->assertEqualsWithDelta(60, $attempts->waitSeconds($key), 2);
        $attempts->fail($key); // 7e
        $this->assertEqualsWithDelta(120, $attempts->waitSeconds($key), 2);

        for ($i = 0; $i < 10; $i++) {
            $attempts->fail($key);
        }
        $this->assertLessThanOrEqual(LoginAttempts::MAX_DELAY, $attempts->waitSeconds($key), 'plafonné à 15 minutes');

        $attempts->clear($key);
        $this->assertSame(0, $attempts->waitSeconds($key), 'une connexion réussie remet tout à zéro');
    }

    public function test_le_blocage_est_propre_a_chaque_compte_et_a_chaque_entreprise(): void
    {
        $attempts = app(LoginAttempts::class);
        for ($i = 0; $i < 6; $i++) {
            $attempts->fail(LoginAttempts::key('t:acme', 'ali@acme.test'));
        }

        $this->assertGreaterThan(0, $attempts->waitSeconds(LoginAttempts::key('t:acme', 'ALI@acme.test')), 'insensible à la casse');
        $this->assertSame(0, $attempts->waitSeconds(LoginAttempts::key('t:acme', 'autre@acme.test')));
        $this->assertSame(0, $attempts->waitSeconds(LoginAttempts::key('t:beta', 'ali@acme.test')));
    }

    public function test_la_console_bloque_apres_cinq_echecs_meme_avec_le_bon_mot_de_passe(): void
    {
        PlatformAdmin::create(['name' => 'Ali', 'email' => 'ali@test.local', 'password' => 'Bon-mot-de-passe-1', 'role' => 'admin', 'is_active' => true]);
        $key = LoginAttempts::key('console', 'ali@test.local');
        for ($i = 0; $i < LoginAttempts::FREE_ATTEMPTS; $i++) {
            app(LoginAttempts::class)->fail($key);
        }

        $this->post('/console/login', ['email' => 'ali@test.local', 'password' => 'Bon-mot-de-passe-1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('admin');

        app(LoginAttempts::class)->clear($key);
        $this->post('/console/login', ['email' => 'ali@test.local', 'password' => 'Bon-mot-de-passe-1'])->assertRedirect();
        $this->assertAuthenticated('admin');
    }

    public function test_turnstile_est_inactif_sans_cle_et_refuse_sans_jeton_avec_cle(): void
    {
        config(['services.turnstile.secret' => null]);
        $this->assertTrue(app(Turnstile::class)->verify(Request::create('/x', 'POST')));

        config(['services.turnstile.secret' => 'secret-de-test']);
        // Deux réponses successives du service : refus, puis acceptation.
        \Illuminate\Support\Facades\Http::fake(['challenges.cloudflare.com/*' => \Illuminate\Support\Facades\Http::sequence()->push(['success' => false])->push(['success' => true])]);
        $this->assertFalse(app(Turnstile::class)->verify(Request::create('/x', 'POST', ['cf-turnstile-response' => 'faux'])));
        $this->assertTrue(app(Turnstile::class)->verify(Request::create('/x', 'POST', ['cf-turnstile-response' => 'bon'])));
    }
}
