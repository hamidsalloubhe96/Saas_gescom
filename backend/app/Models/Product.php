<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    // stock_quantity n'est volontairement PAS fillable : il ne change que via StockService.
    protected $fillable = [
        'sku', 'barcode', 'name', 'category_id', 'unit', 'purchase_price', 'sale_price',
        'tax_rate', 'min_stock', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'stock_quantity' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
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
            if (($this->attributes['branch_price'] ?? null) !== null) {
                $this->attributes['sale_price'] = $this->attributes['branch_price'];
            }
            unset($this->attributes['branch_stock'], $this->attributes['branch_price']);
        }

        return $this;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
