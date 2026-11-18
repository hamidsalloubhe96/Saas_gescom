<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'address', 'city', 'tax_id', 'credit_limit', 'notes', 'is_active', 'is_walk_in',
    ];

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'is_active' => 'boolean', 'is_walk_in' => 'boolean'];
    }
}
