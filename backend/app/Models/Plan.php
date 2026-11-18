<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends CentralModel
{
    protected $fillable = [
        'slug', 'name', 'description', 'price', 'currency', 'period_months', 'max_users', 'max_products',
        'max_invoices_month', 'max_branches', 'tiers', 'is_trial', 'trial_days', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2', 'tiers' => 'array', 'is_trial' => 'boolean', 'is_active' => 'boolean',
            'max_users' => 'integer', 'max_products' => 'integer', 'max_invoices_month' => 'integer', 'max_branches' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Libellé d'une limite : « 10 » ou « Illimité ». */
    public static function limitLabel(?int $limit): string
    {
        return $limit === null ? 'Illimité' : number_format($limit, 0, ',', ' ');
    }
}
