<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    // stock_quantity n'est volontairement PAS fillable : il ne change que via StockService.
    protected $fillable = [
        'sku', 'barcode', 'name', 'active_ingredient', 'dosage', 'form', 'track_batches', 'category_id', 'unit', 'plu', 'is_manufactured', 'batch_yield', 'sellable', 'sold_by_weight', 'min_sale_quantity', 'purchase_price', 'sale_price',
        'tax_rate', 'min_stock', 'notes', 'is_active',
    ];

    protected $appends = ['recipe_cost', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'stock_quantity' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
            'track_batches' => 'boolean',
            'sold_by_weight' => 'boolean',
            'is_manufactured' => 'boolean',
            'sellable' => 'boolean',
            'batch_yield' => 'decimal:3',
            'min_sale_quantity' => 'decimal:3',
        ];
    }

    /** Ajoute le stock et le prix LOCAUX d'un point de vente (colonnes branch_stock / branch_price), voir applyBranch(). */
    public function scopeForBranch($query, ?int $branchId)
    {
        if (! $branchId) {
            return $query;
        }

        return $query->select('products.*')->addSelect([
            'branch_stock' => StockLevel::select('quantity')->whereColumn('stock_levels.product_id', 'products.id')->where('stock_levels.branch_id', $branchId)->limit(1),
            'branch_price' => BranchPrice::select('sale_price')->whereColumn('branch_prices.product_id', 'products.id')->where('branch_prices.branch_id', $branchId)->limit(1),
        ]);
    }

    /** Remplace stock_quantity et sale_price par les valeurs du point de vente (affichage uniquement, jamais enregistré). */
    public function applyBranch(): static
    {
        if (array_key_exists('branch_stock', $this->attributes) || array_key_exists('branch_price', $this->attributes)) {
            $this->attributes['stock_quantity'] = $this->attributes['branch_stock'] ?? 0;
            // Un prix propre au point de vente remplace le prix du catalogue ; les paliers ne s'appliquent alors plus.
            $this->attributes['local_price'] = ($this->attributes['branch_price'] ?? null) !== null;
            if ($this->attributes['local_price']) {
                $this->attributes['sale_price'] = $this->attributes['branch_price'];
            }
            unset($this->attributes['branch_stock'], $this->attributes['branch_price']);
        }

        return $this;
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class)->orderBy('quantity')->orderBy('id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(ProductRecipeItem::class)->orderBy('id');
    }

    /** Coût d'une fournée (prix d'achat des ingrédients) ; null si la recette ou les ingrédients ne sont pas chargés. */
    public function getRecipeCostAttribute(): ?float
    {
        if (! $this->is_manufactured || ! $this->relationLoaded('recipeItems') || $this->recipeItems->contains(fn ($i) => ! $i->relationLoaded('ingredient'))) {
            return null;
        }

        return round($this->recipeItems->sum(fn ($i) => (float) $i->quantity * (float) ($i->ingredient?->purchase_price ?? 0)), 2);
    }

    /** Coût d'une pièce fabriquée : coût de la fournée / rendement. */
    public function getUnitCostAttribute(): ?float
    {
        $cost = $this->recipe_cost;

        return ($cost !== null && (float) $this->batch_yield > 0) ? round($cost / (float) $this->batch_yield, 2) : null;
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('quantity');
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('min_quantity');
    }

    /** Prix unitaire de l'unité de base pour une quantité donnée : celui du plus haut palier atteint, sinon le prix normal. */
    public function tierPrice(float $quantity, ?float $basePrice = null): float
    {
        $price = $basePrice ?? (float) $this->sale_price;
        foreach ($this->priceTiers as $tier) {
            if ($quantity >= (float) $tier->min_quantity) {
                $price = (float) $tier->unit_price;
            }
        }

        return $price;
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
