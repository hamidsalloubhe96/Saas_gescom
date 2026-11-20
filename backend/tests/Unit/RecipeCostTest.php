<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductRecipeItem;
use App\Services\ProductionService;
use App\Services\StockService;
use PHPUnit\Framework\TestCase;

class RecipeCostTest extends TestCase
{
    /** Baguette : une fournée de 40 pièces = 10 kg de farine (500), 0,2 kg de levure (2 500), 0,2 kg de sel (200). */
    private function baguette(): Product
    {
        $ingredient = fn (float $price) => new Product(['purchase_price' => $price]);
        $item = fn (float $qty, Product $ing) => tap(new ProductRecipeItem(['quantity' => $qty]), fn ($i) => $i->setRelation('ingredient', $ing));

        $p = new Product(['name' => 'Baguette', 'is_manufactured' => true, 'batch_yield' => 40, 'sale_price' => 175]);
        $p->setRelation('recipeItems', collect([
            $item(10, $ingredient(500)), $item(0.2, $ingredient(2500)), $item(0.2, $ingredient(200)),
        ]));

        return $p;
    }

    public function test_le_cout_de_la_fournee_et_d_une_piece(): void
    {
        $p = $this->baguette();

        $this->assertSame(5540.0, $p->recipe_cost);
        $this->assertSame(138.5, $p->unit_cost);
        $this->assertSame(138.5, (new ProductionService(new StockService))->unitCost($p));
    }

    public function test_sans_recette_ou_sans_rendement_il_n_y_a_pas_de_cout(): void
    {
        $this->assertNull((new Product(['is_manufactured' => false]))->recipe_cost);

        $p = $this->baguette();
        $p->batch_yield = 0;

        $this->assertNull($p->unit_cost);
        $this->assertNull((new ProductionService(new StockService))->unitCost($p));
    }

    public function test_un_produit_achete_n_a_pas_de_cout_de_recette(): void
    {
        $p = $this->baguette();
        $p->is_manufactured = false;

        $this->assertNull($p->recipe_cost);
    }
}
