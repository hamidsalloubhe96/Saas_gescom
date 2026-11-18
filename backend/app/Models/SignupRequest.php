<?php

namespace App\Models;

class SignupRequest extends CentralModel
{
    public const STATUSES = ['new' => 'Nouvelle', 'contacted' => 'Contactée', 'converted' => 'Convertie', 'rejected' => 'Rejetée'];

    protected $fillable = ['name', 'email', 'phone', 'company', 'country', 'message', 'status', 'handled_at', 'handled_by', 'tenant_id', 'ip'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}
