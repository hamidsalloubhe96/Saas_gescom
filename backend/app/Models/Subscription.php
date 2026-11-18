<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends CentralModel
{
    public const STATUSES = [
        'trial' => 'Essai', 'active' => 'Actif', 'past_due' => 'En retard', 'expired' => 'Expiré', 'cancelled' => 'Résilié',
    ];

    protected $fillable = [
        'tenant_id', 'plan_id', 'status', 'trial_ends_at', 'current_period_start', 'current_period_end', 'grace_ends_at',
        'cancelled_at', 'custom_limits', 'limits_unlocked_until', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime', 'current_period_start' => 'datetime', 'current_period_end' => 'datetime',
            'grace_ends_at' => 'datetime', 'cancelled_at' => 'datetime', 'limits_unlocked_until' => 'datetime',
            'custom_limits' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->latest('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Date de fin de la période en cours : fin d'essai ou prochaine échéance. */
    public function endsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->status === 'trial' ? $this->trial_ends_at : ($this->current_period_end ?? $this->trial_ends_at);
    }

    /** Jours restants (négatif = en retard). Null si aucune échéance. */
    public function daysLeft(): ?int
    {
        $end = $this->endsAt();

        return $end ? (int) floor(now()->diffInRealDays($end, false)) : null;
    }

    public function limitsUnlocked(): bool
    {
        return $this->limits_unlocked_until !== null && $this->limits_unlocked_until->isFuture();
    }
}
