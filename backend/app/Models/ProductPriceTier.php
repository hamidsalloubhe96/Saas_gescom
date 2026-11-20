<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Palier de prix : à partir de « min_quantity » unités de base, le prix unitaire devient « unit_price ». */
class ProductPriceTier extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'min_quantity', 'unit_price'];

    protected function casts(): array
    {
        return ['min_quantity' => 'decimal:3', 'unit_price' => 'decimal:2'];
    }
}
