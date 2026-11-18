<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $id, string $name, string $status = 'active'): void
    {
        Tenant::withoutEvents(fn () => Tenant::create(['id' => $id, 'name' => $name, 'status' => $status, 'owner_email' => "o@$id.test"]));
    }

    public function test_la_recherche_trouve_par_nom_ou_identifiant_et_ne_renvoie_que_le_necessaire(): void
    {
        $this->tenant('boutique_diallo', 'Boutique Diallo');
        $this->tenant('quincaillerie_sow', 'Quincaillerie Sow');

        $r = $this->getJson('/api/public/companies?q=diall')->assertOk();
        $this->assertSame([['slug' => 'boutique_diallo', 'name' => 'Boutique Diallo']], $r->json());
        $this->assertSame('quincaillerie_sow', $this->getJson('/api/public/companies?q=SOW')->json('0.slug'));
    }

    public function test_la_recherche_exclut_les_entreprises_non_actives_et_les_saisies_courtes(): void
    {
        $this->tenant('suspendue', 'Boutique Suspendue', 'suspended');
        $this->tenant('active_co', 'Boutique Active');

        $this->assertCount(1, $this->getJson('/api/public/companies?q=boutique')->json());
        $this->assertSame([], $this->getJson('/api/public/companies?q=b')->json());
        $this->assertSame([], $this->getJson('/api/public/companies')->json());
        $this->assertSame([], $this->getJson('/api/public/companies?q=%25')->json(), 'les jokers sont littéraux');
    }

    public function test_forfaits_publics_et_contact(): void
    {
        PlatformSetting::put('contact_phone', '+221 77 000 00 00');

        $plans = $this->getJson('/api/public/plans')->assertOk()->json();
        $this->assertNotEmpty($plans);
        $this->assertArrayNotHasKey('id', $plans[0]);
        $this->getJson('/api/public/config')->assertJsonPath('contact_phone', '+221 77 000 00 00');
    }
}
