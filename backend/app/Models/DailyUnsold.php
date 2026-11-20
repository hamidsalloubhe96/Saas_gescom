<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bilan d'une journée pour un produit fabriqué : produit, vendu, invendu, et ce qui a été mis au rebut. */
class DailyUnsold extends Model
{
    protected $table = 'daily_unsold';

    protected $fillable = ['day', 'branch_id', 'product_id', 'produced', 'sold', 'unsold', 'discarded', 'loss', 'user_id'];

    protected function casts(): array
    {
        return ['day' => 'date:Y-m-d', 'produced' => 'decimal:3', 'sold' => 'decimal:3', 'unsold' => 'decimal:3', 'discarded' => 'decimal:3', 'loss' => 'decimal:2'];
    }
}
