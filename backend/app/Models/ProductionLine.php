<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['production_id', 'product_id', 'quantity', 'batches', 'unit_cost'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'batches' => 'decimal:3', 'unit_cost' => 'decimal:2'];
    }
}
