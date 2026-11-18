<?php

namespace Tests\Feature;

use App\Http\Middleware\HeadquartersOnly;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\CurrentBranch;
use App\Services\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Points de vente : droits, limite par forfait (console) et restriction au siège. Les parcours de données sont vérifiés sur PostgreSQL. */
class BranchesTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): PlatformAdmin
    {
        $a = PlatformAdmin::create(['name' => 'Root', 'email' => 'root@test.local', 'password' => 'Password-12345', 'role' => 'super_admin', 'is_active' => true]);
        $a->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $a->fresh();
    }

    public function test_les_droits_des_roles(): void
    {
        $roles = config('permissions.roles');
        $manager = array_diff(array_keys(config('permissions.permissions')), ['users.manage']);

        foreach (['branches.manage', 'transfers.manage', 'transfers.receive'] as $p) {
            $this->assertContains($p, $roles['manager']['permissions']);
            $this->assertContains($p, $manager);
            $this->assertNotContains($p, $roles['seller']['permissions']);
            $this->assertNotContains($p, $roles['cashier']['permissions']);
        }
        $this->assertContains('transfers.receive', $roles['storekeeper']['permissions']);
        $this->assertNotContains('branches.manage', $roles['storekeeper']['permissions']);
    }

    public function test_un_utilisateur_de_point_de_vente_est_toujours_limite_a_la_sienne(): void
    {
        $user = new User(['name' => 'Resp', 'email' => 'r@t.test', 'role' => 'manager', 'branch_id' => 7]);
        $this->actingAs($user);
        app(CurrentBranch::class)->reset();

        $this->assertSame(7, app(CurrentBranch::class)->id());
        $this->assertSame(7, app(CurrentBranch::class)->forWrite());
        $this->assertTrue(app(CurrentBranch::class)->isRestricted());

        // L'en-tête X-Branch n'a aucun effet pour lui.
        app(CurrentBranch::class)->reset();
        request()->headers->set('X-Branch', '99');
        $this->assertSame(7, app(CurrentBranch::class)->id());
    }

    public function test_le_middleware_siege_refuse_un_point_de_vente_et_laisse_passer_le_siege(): void
    {
        $mw = new HeadquartersOnly;
        $next = fn () => response('ok');

        $this->actingAs(new User(['name' => 'Resp', 'email' => 'r@t.test', 'role' => 'manager', 'branch_id' => 3]));
        $denied = $mw->handle(Request::create('/api/purchases'), $next);
        $this->assertSame(403, $denied->getStatusCode());
        $this->assertSame('headquarters_only', json_decode($denied->getContent(), true)['code']);

        $this->actingAs(new User(['name' => 'HQ', 'email' => 'h@t.test', 'role' => 'manager', 'branch_id' => null]));
        $this->assertSame(200, $mw->handle(Request::create('/api/purchases'), $next)->getStatusCode());
    }

    public function test_les_points_de_vente_sont_une_ressource_limitable_du_forfait(): void
    {
        $this->assertArrayHasKey('branches', PlanLimits::RESOURCES);
        $this->assertTrue(Plan::first()->isFillable('max_branches'));
        $this->assertNull(Plan::first()->max_branches, 'illimitées par défaut');
    }

    public function test_la_console_enregistre_la_limite_de_points_de_vente_d_un_forfait(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');
        $plan = Plan::where('is_trial', false)->first();

        $this->get("/console/forfaits/{$plan->id}/modifier")->assertOk()->assertSee('Points de vente');
        $this->put("/console/forfaits/{$plan->id}", [
            'name' => $plan->name, 'price' => $plan->price, 'currency' => $plan->currency, 'period_months' => $plan->period_months,
            'max_branches' => 5, 'sort' => $plan->sort,
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, $plan->fresh()->max_branches);
        $this->get('/console/forfaits')->assertOk()->assertSee('Points de vente');
    }

    public function test_un_point_de_vente_ne_peut_pas_modifier_l_en_tete_pour_voir_le_siege(): void
    {
        $this->actingAs(new User(['name' => 'Resp', 'email' => 'r@t.test', 'role' => 'manager', 'branch_id' => 4]));
        app(CurrentBranch::class)->reset();
        request()->headers->set('X-Branch', '1');

        $this->assertSame(4, app(CurrentBranch::class)->id());
    }
}
