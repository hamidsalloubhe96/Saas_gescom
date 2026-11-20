<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lot d'un produit dans un point de vente : numéro, date de péremption et quantité restante. */
class ProductBatch extends Model
{
    protected $fillable = ['product_id', 'branch_id', 'batch_number', 'expires_at', 'quantity'];

    protected function casts(): array
    {
        return ['expires_at' => 'date:Y-m-d', 'quantity' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
