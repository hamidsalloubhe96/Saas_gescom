<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegisterSession extends Model
{
    use \App\Models\Concerns\BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'opened_by', 'closed_by', 'opening_amount', 'opened_at', 'closed_at', 'expected_cash',
        'counted_cash', 'difference', 'total_sales', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_amount' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
