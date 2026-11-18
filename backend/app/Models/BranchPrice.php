<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchPrice extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'branch_id', 'sale_price'];
}
