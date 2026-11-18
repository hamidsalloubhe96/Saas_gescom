<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['stock_transfer_id', 'product_id', 'quantity', 'received_quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'received_quantity' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->select('id', 'name', 'sku', 'unit');
    }
}
