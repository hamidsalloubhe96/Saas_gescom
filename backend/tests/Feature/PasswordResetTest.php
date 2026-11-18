<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordCode;
use App\Models\PlatformAdmin;
use App\Services\PasswordResetCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $active = true): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'Ali', 'email' => 'ali@test.local', 'password' => 'Ancien-mdp-123', 'role' => 'admin', 'is_active' => $active]);
    }

    private function codeSent(): string
    {
        $code = null;
        Mail::assertSent(ResetPasswordCode::class, function ($m) use (&$code) { $code = $m->code; return true; });

        return $code;
    }

    public function test_le_code_est_hache_unique_et_limite_a_cinq_essais(): void
    {
        Mail::fake();
        $svc = app(PasswordResetCode::class);
        $svc->send('t:acme', 'a@b.test', 'Acme');
        $code = $this->codeSent();

        $this->assertStringNotContainsString($code, json_encode(Cache::get('pwreset:'.sha1('t:acme|a@b.test'))));
        $this->assertFalse($svc->consume('t:autre', 'a@b.test', $code), 'un code ne vaut que pour son espace');
        $this->assertTrue($svc->consume('t:acme', 'A@B.test ', $code));
        $this->assertFalse($svc->consume('t:acme', 'a@b.test', $code), 'usage unique');

        $svc->send('t:acme', 'a@b.test', 'Acme');
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($svc->consume('t:acme', 'a@b.test', $wrong));
        }
        $this->assertFalse($svc->consume('t:acme', 'a@b.test', $this->lastCode()), 'après 5 essais le code est invalidé');
    }

    private function lastCode(): string
    {
        $code = null;
        Mail::assertSent(ResetPasswordCode::class, function ($m) use (&$code) { $code = $m->code; return true; });

        return $code;
    }

    public function test_renvoi_limite_a_un_par_minute(): void
    {
        Mail::fake();
        $svc = app(PasswordResetCode::class);

        $this->assertSame(0, $svc->waitSeconds('console', 'a@b.test'));
        $svc->send('console', 'a@b.test', 'x');
        $this->assertGreaterThan(0, $svc->waitSeconds('console', 'a@b.test'));
        $this->assertLessThanOrEqual(60, $svc->waitSeconds('console', 'a@b.test'));
    }

    public function test_parcours_console_complet(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->get('/console/login')->assertOk()->assertSee('Mot de passe oublié');
        $this->get('/console/reinitialisation')->assertNotFound();
        $this->post('/console/mot-de-passe-oublie', ['email' => 'ali@test.local'])->assertRedirect('/console/reinitialisation');
        $code = $this->codeSent();

        $this->post('/console/reinitialisation', ['code' => '000000' === $code ? '111111' : '000000', 'password' => 'Nouveau-mdp-123', 'password_confirmation' => 'Nouveau-mdp-123'])->assertSessionHasErrors('code');
        $this->post('/console/reinitialisation', ['code' => $code, 'password' => 'court', 'password_confirmation' => 'court'])->assertSessionHasErrors('password');
        $this->post('/console/reinitialisation', ['code' => $code, 'password' => 'Nouveau-mdp-123', 'password_confirmation' => 'Nouveau-mdp-123'])->assertRedirect('/console/login');

        $this->assertTrue(Hash::check('Nouveau-mdp-123', $admin->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset']);
        $this->post('/console/reinitialisation', ['code' => $code, 'password' => 'Autre-mdp-12345', 'password_confirmation' => 'Autre-mdp-12345'])->assertRedirect('/console/mot-de-passe-oublie');
    }

    public function test_console_compte_inconnu_ou_desactive_ne_recoit_rien(): void
    {
        Mail::fake();
        $this->admin(false);

        $this->post('/console/mot-de-passe-oublie', ['email' => 'inconnu@test.local'])->assertSessionHasErrors('email');
        $this->post('/console/mot-de-passe-oublie', ['email' => 'ali@test.local'])->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_console_pas_de_second_envoi_pendant_la_minute(): void
    {
        Mail::fake();
        $this->admin();

        $this->post('/console/mot-de-passe-oublie', ['email' => 'ali@test.local'])->assertRedirect('/console/reinitialisation');
        $this->post('/console/mot-de-passe-oublie', ['email' => 'ali@test.local'])->assertSessionHasErrors('email');
        Mail::assertSent(ResetPasswordCode::class, 1);
    }

    public function test_les_routes_entreprise_existent_hors_authentification(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => $r->methods()[0].' '.$r->uri());

        $this->assertTrue($routes->contains('POST api/password/forgot'));
        $this->assertTrue($routes->contains('POST api/password/reset'));
        $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create('/api/password/forgot', 'POST'));
        $this->assertNotContains('auth:sanctum', $route->gatherMiddleware());
    }
}
