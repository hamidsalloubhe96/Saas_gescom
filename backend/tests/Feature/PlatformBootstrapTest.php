<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_le_premier_admin_une_seule_fois(): void
    {
        config(['bootstrap.admin_email' => 'Chef@Example.com', 'bootstrap.admin_password' => 'motdepasse-solide', 'bootstrap.admin_name' => 'Chef']);

        $this->artisan('platform:bootstrap')->assertSuccessful();
        $this->artisan('platform:bootstrap')->assertSuccessful();

        $admin = PlatformAdmin::where('email', 'chef@example.com')->sole();
        $this->assertSame('super_admin', $admin->role);
        $this->assertFalse((bool) $admin->must_change_password);
    }

    public function test_sans_variables_ou_mot_de_passe_trop_court_rien_nest_cree(): void
    {
        $this->artisan('platform:bootstrap')->assertSuccessful();
        config(['bootstrap.admin_email' => 'chef@example.com', 'bootstrap.admin_password' => 'court']);
        $this->artisan('platform:bootstrap')->assertSuccessful();

        $this->assertSame(0, PlatformAdmin::count());
    }
}
