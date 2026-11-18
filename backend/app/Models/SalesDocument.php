<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesDocument extends Model
{
    use \App\Models\Concerns\BelongsToBranch;

    protected $fillable = [
        'branch_id', 'type', 'channel', 'register_session_id', 'number', 'customer_id', 'status', 'issue_date', 'due_date', 'subtotal',
        'tax_total', 'total', 'amount_paid', 'notes', 'source_quote_id', 'user_id',
    ];

    protected $appends = ['balance'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    /** Reste à payer (TTC - déjà payé). */
    public function getBalanceAttribute(): string
    {
        return number_format(round((float) $this->total - (float) $this->amount_paid, 2), 2, '.', '');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesDocumentLine::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at')->orderBy('id');
    }

    public function sourceQuote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_quote_id');
    }

    /** Facture générée à partir de ce devis (le cas échéant). */
    public function invoice(): HasOne
    {
        return $this->hasOne(self::class, 'source_quote_id');
    }
}
