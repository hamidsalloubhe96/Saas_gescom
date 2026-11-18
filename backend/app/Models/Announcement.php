<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Announcement extends CentralModel
{
    public const LEVELS = ['info' => 'Information', 'warning' => 'Avertissement', 'update' => 'Nouveauté'];

    protected $fillable = ['title', 'body', 'level', 'is_active', 'starts_at', 'ends_at', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** Annonces visibles maintenant : actives et dans leur fenêtre de dates. */
    public function scopeCurrent(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
