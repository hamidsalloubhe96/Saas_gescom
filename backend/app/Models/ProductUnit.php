<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Conditionnement d'un produit : « Carton » = 12 unités de base, avec son prix et éventuellement son code-barres. */
class ProductUnit extends Model
{
    protected $fillable = ['product_id', 'name', 'quantity', 'sale_price', 'barcode'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'sale_price' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
