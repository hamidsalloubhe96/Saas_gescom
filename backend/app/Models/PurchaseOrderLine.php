<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id', 'product_id', 'position', 'description', 'unit', 'quantity',
        'quantity_received', 'unit_price', 'tax_rate', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}
