<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends CentralModel
{
    public $timestamps = false;

    protected $fillable = ['admin_id', 'admin_name', 'action', 'tenant_id', 'description', 'meta', 'ip', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'admin_id');
    }

    /**
     * Enregistre une action de la console. À appeler pour TOUTE action (connexion, suspension, création…).
     */
    public static function record(string $action, string $description, ?string $tenantId = null, array $meta = [], ?PlatformAdmin $admin = null): self
    {
        $admin ??= auth('admin')->user();
        $request = request();

        return self::create([
            'admin_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'action' => $action,
            'tenant_id' => $tenantId,
            'description' => mb_substr($description, 0, 500),
            'meta' => $meta ?: null,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'created_at' => now(),
        ]);
    }
}
