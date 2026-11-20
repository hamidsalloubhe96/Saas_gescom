<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductPriceTier;
use PHPUnit\Framework\TestCase;

class ProductPricingTest extends TestCase
{
    private function product(float $price, array $tiers): Product
    {
        $p = new Product(['sale_price' => $price]);
        $p->setRelation('priceTiers', collect($tiers)->map(fn ($t) => new ProductPriceTier(['min_quantity' => $t[0], 'unit_price' => $t[1]]))->values());

        return $p;
    }

    public function test_sans_palier_le_prix_normal_s_applique(): void
    {
        $this->assertSame(1500.0, $this->product(1500, [])->tierPrice(100));
    }

    public function test_le_plus_haut_palier_atteint_donne_le_prix(): void
    {
        $p = $this->product(1500, [[10, 1400], [50, 1300]]);

        $this->assertSame(1500.0, $p->tierPrice(1));
        $this->assertSame(1500.0, $p->tierPrice(9.999));
        $this->assertSame(1400.0, $p->tierPrice(10));
        $this->assertSame(1400.0, $p->tierPrice(49));
        $this->assertSame(1300.0, $p->tierPrice(50));
        $this->assertSame(1300.0, $p->tierPrice(500));
    }

    public function test_un_prix_de_base_explicite_remplace_celui_du_catalogue(): void
    {
        $p = $this->product(1500, [[10, 1400]]);

        $this->assertSame(1600.0, $p->tierPrice(5, 1600.0));
        $this->assertSame(1400.0, $p->tierPrice(10, 1600.0));
    }
}
