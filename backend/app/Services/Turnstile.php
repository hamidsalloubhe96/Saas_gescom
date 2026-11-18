<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Contrôle anti-robot Cloudflare Turnstile, actif seulement si la clé secrète est configurée. */
class Turnstile
{
    public function enabled(): bool
    {
        return (bool) config('services.turnstile.secret');
    }

    public function verify(Request $request, string $field = 'cf-turnstile-response'): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        try {
            return (bool) Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret'),
                'response' => $request->input($field),
                'remoteip' => $request->ip(),
            ])->json('success', false);
        } catch (\Throwable) {
            return false; // en cas de doute (service injoignable), on refuse plutôt que de laisser passer un robot
        }
    }
}
