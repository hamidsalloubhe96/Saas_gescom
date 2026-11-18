<?php

namespace App\Models;

class LegalText extends CentralModel
{
    public const KEYS = [
        'terms' => "Conditions d'utilisation",
        'privacy' => 'Politique de confidentialité',
        'subscription' => "Conditions d'abonnement",
        'legal_notice' => 'Mentions légales',
    ];

    protected $fillable = ['key', 'version', 'title', 'body', 'status', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'published_at' => 'datetime'];
    }

    /** Version en vigueur d'un texte (la dernière publiée). */
    public static function current(string $key): ?self
    {
        return static::where('key', $key)->where('status', 'published')->orderByDesc('version')->first();
    }

    public static function draft(string $key): self
    {
        return static::firstOrCreate(['key' => $key, 'status' => 'draft'], ['title' => self::KEYS[$key] ?? $key, 'body' => '']);
    }
}
