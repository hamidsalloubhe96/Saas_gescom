<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesDocumentLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sales_document_id', 'product_id', 'position', 'description', 'unit', 'quantity',
        'unit_price', 'discount_rate', 'tax_rate', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}
