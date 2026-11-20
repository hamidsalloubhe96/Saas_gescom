<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Production extends Model
{
    protected $fillable = ['produced_on', 'branch_id', 'user_id'];

    protected function casts(): array
    {
        return ['produced_on' => 'date:Y-m-d'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionLine::class);
    }
}
