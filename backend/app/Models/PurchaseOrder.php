<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'number', 'supplier_id', 'status', 'order_date', 'expected_date', 'subtotal',
        'tax_total', 'total', 'amount_paid', 'notes', 'user_id',
    ];

    protected $appends = ['balance'];

    protected function casts(): array
    {
        return [
            'order_date' => 'date:Y-m-d',
            'expected_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    /** Reste à payer au fournisseur. */
    public function getBalanceAttribute(): string
    {
        return number_format(round((float) $this->total - (float) $this->amount_paid, 2), 2, '.', '');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class)->orderBy('paid_at')->orderBy('id');
    }
}
