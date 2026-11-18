<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    protected $fillable = ['purchase_order_id', 'amount', 'method', 'paid_at', 'reference', 'user_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'date:Y-m-d'];
    }
}
