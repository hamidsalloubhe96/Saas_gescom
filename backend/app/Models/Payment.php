<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use \App\Models\Concerns\BelongsToBranch;

    protected $fillable = ['branch_id', 'sales_document_id', 'register_session_id', 'amount', 'method', 'paid_at', 'reference', 'user_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'date:Y-m-d'];
    }
}
