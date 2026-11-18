<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'address', 'city', 'tax_id', 'payment_terms_days', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['payment_terms_days' => 'integer', 'is_active' => 'boolean'];
    }
}
