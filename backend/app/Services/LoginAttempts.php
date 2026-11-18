<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Blocage progressif des tentatives de connexion, PAR COMPTE (et non par adresse IP, que les robots changent sans cesse).
 * Les 5 premiers échecs sont libres ; ensuite le délai double à chaque échec (30 s, 1 min, 2 min…) jusqu'à 15 minutes.
 * Le blocage est temporaire et se vide à la première connexion réussie : un compte n'est jamais verrouillé définitivement.
 */
class LoginAttempts
{
    public const FREE_ATTEMPTS = 5;
    public const FIRST_DELAY = 30;     // secondes
    public const MAX_DELAY = 900;      // 15 minutes
    private const MEMORY_MINUTES = 60; // les échecs sont oubliés après une heure sans tentative

    public static function key(string $scope, string $identifier): string
    {
        return 'login_fail:'.sha1($scope.'|'.strtolower(trim($identifier)));
    }

    /** Secondes à attendre avant une nouvelle tentative (0 = autorisé). */
    public function waitSeconds(string $key): int
    {
        $until = (int) (Cache::get($key)['until'] ?? 0);

        return max(0, $until - time());
    }

    public function fail(string $key): void
    {
        $entry = Cache::get($key, ['count' => 0, 'until' => 0]);
        $count = $entry['count'] + 1;
        $until = $count >= self::FREE_ATTEMPTS
            ? time() + min(self::MAX_DELAY, self::FIRST_DELAY * (2 ** ($count - self::FREE_ATTEMPTS)))
            : 0;

        Cache::put($key, ['count' => $count, 'until' => $until], now()->addMinutes(self::MEMORY_MINUTES));
    }

    public function clear(string $key): void
    {
        Cache::forget($key);
    }

    public static function message(int $seconds): string
    {
        $text = $seconds >= 60 ? ceil($seconds / 60).' min' : $seconds.' s';

        return "Trop de tentatives échouées. Réessayez dans {$text}.";
    }
}
