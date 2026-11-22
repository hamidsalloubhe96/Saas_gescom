<?php

namespace Tests\Unit;

use App\Models\Tenant;
use PHPUnit\Framework\TestCase;

class TenantModulesTest extends TestCase
{
    private function tenant(string $type, mixed $modules = null): Tenant
    {
        $t = new Tenant;
        $t->setRawAttributes(['business_type' => $type, 'modules' => $modules === null ? null : json_encode($modules)]);

        return $t;
    }

    public function test_la_pharmacie_suit_les_peremptions_par_defaut(): void
    {
        $this->assertSame(['expiry'], $this->tenant('pharmacy')->enabledModules());
        $this->assertTrue($this->tenant('pharmacy')->hasModule('expiry'));
    }

    public function test_la_boulangerie_fabrique_par_defaut(): void
    {
        $this->assertSame(['production'], $this->tenant('bakery')->enabledModules());
    }

    public function test_activer_un_module_ne_touche_pas_aux_autres(): void
    {
        $this->assertSame(['production', 'expiry'], $this->tenant('bakery')->modulesWith(['expiry' => true]));
        $this->assertSame(['production'], $this->tenant('bakery')->modulesWith(['expiry' => false]));
        $this->assertSame([], $this->tenant('bakery')->modulesWith(['production' => false]));
        $this->assertSame(['expiry'], $this->tenant('pharmacy')->modulesWith(['production' => false, 'inconnu' => true]));
        $this->assertSame(['production'], $this->tenant('general')->modulesWith(['production' => true, 'production ' => true]));
    }

    public function test_les_autres_activites_n_ont_aucun_module_par_defaut(): void
    {
        foreach (['general', 'hardware', 'butcher', 'wholesale', 'services'] as $type) {
            $this->assertSame([], $this->tenant($type)->enabledModules(), $type);
        }
    }

    public function test_le_choix_de_l_entreprise_prime_sur_le_type(): void
    {
        $this->assertSame(['expiry'], $this->tenant('general', ['expiry'])->enabledModules());
        $this->assertSame([], $this->tenant('pharmacy', [])->enabledModules());
    }

    public function test_un_module_inconnu_est_ignore(): void
    {
        $this->assertSame(['expiry'], $this->tenant('general', ['expiry', 'inconnu'])->enabledModules());
    }

    public function test_les_types_d_activite_disponibles(): void
    {
        $this->assertSame('Boucherie et produits au poids', Tenant::BUSINESS_TYPES['butcher']);
        $this->assertArrayHasKey('hardware', Tenant::BUSINESS_TYPES);
        $this->assertArrayHasKey('wholesale', Tenant::BUSINESS_TYPES);
    }
}
