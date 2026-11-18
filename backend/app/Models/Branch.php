<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['name', 'code', 'region', 'city', 'address', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_main' => 'boolean', 'is_active' => 'boolean'];
    }
}
