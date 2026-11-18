<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'branch_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }
}
