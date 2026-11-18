<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends CentralModel
{
    public const METHODS = [
        'cash' => 'Espèces', 'transfer' => 'Virement', 'mobile_money' => 'Mobile money', 'card' => 'Carte', 'other' => 'Autre',
    ];

    protected $fillable = [
        'tenant_id', 'subscription_id', 'plan_id', 'amount', 'currency', 'months', 'method', 'reference', 'status', 'source',
        'paid_at', 'validated_by', 'validated_at', 'note',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'date', 'validated_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'validated_by');
    }

    /** Numéro de reçu stable, dérivé de l'identifiant : REC-2026-000012. */
    public function receiptNumber(): string
    {
        return sprintf('REC-%s-%06d', ($this->validated_at ?? $this->created_at)->format('Y'), $this->id);
    }

    /** Données d'un reçu (uniquement pour un paiement validé). */
    public function receipt(): array
    {
        abort_if($this->status !== 'validated', 404, 'Aucun reçu : ce paiement n\'est pas validé.');
        $this->loadMissing(['tenant', 'plan']);

        return [
            'number' => $this->receiptNumber(),
            'company' => $this->tenant?->name ?? $this->tenant_id,
            'plan' => $this->plan?->name,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'months' => $this->months,
            'method' => $this->methodLabel(),
            'reference' => $this->reference,
            'paid_at' => $this->paid_at?->toDateString(),
            'validated_at' => $this->validated_at?->toDateString(),
            'issuer' => config('app.name'),
        ];
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }
}
