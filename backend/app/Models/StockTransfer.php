<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    public const STATUSES = ['requested' => 'Demandé', 'shipped' => 'Expédié', 'received' => 'Reçu', 'cancelled' => 'Annulé'];

    protected $fillable = ['number', 'from_branch_id', 'to_branch_id', 'status', 'note', 'requested_by', 'shipped_by', 'received_by', 'shipped_at', 'received_at'];

    protected function casts(): array
    {
        return ['shipped_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class)->orderBy('id');
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }
}
