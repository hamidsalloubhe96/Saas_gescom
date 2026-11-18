<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends CentralModel
{
    public const STATUSES = ['open' => 'Ouvert', 'answered' => 'Répondu', 'closed' => 'Clos'];

    protected $fillable = ['tenant_id', 'author_name', 'author_email', 'subject', 'status', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->oldest('id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
