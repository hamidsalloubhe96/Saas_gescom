<?php

namespace App\Services;

use App\Mail\ResetPasswordCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Code à 6 chiffres de réinitialisation de mot de passe, envoyé par e-mail.
 * Stocké haché, valable 15 minutes, 5 essais, usage unique, renvoi possible après 60 s.
 * Le « scope » sépare les espaces : une entreprise précise ("t:acme") ou la console ("console").
 */
class PasswordResetCode
{
    public const TTL_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;
    public const COOLDOWN_SECONDS = 60;

    private function norm(string $email): string
    {
        return strtolower(trim($email));
    }

    private function key(string $scope, string $email): string
    {
        return 'pwreset:'.sha1($scope.'|'.$this->norm($email));
    }

    private function hash(string $scope, string $email, string $code): string
    {
        return hash_hmac('sha256', $scope.'|'.$this->norm($email).'|'.$code, (string) config('app.key'));
    }

    /** Secondes à attendre avant un nouveau code (0 = on peut envoyer). */
    public function waitSeconds(string $scope, string $email): int
    {
        $until = Cache::get($this->key($scope, $email).':cooldown');

        return $until ? max(0, $until - time()) : 0;
    }

    public function send(string $scope, string $email, string $audience): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->key($scope, $email), ['hash' => $this->hash($scope, $email, $code), 'attempts' => 0], now()->addMinutes(self::TTL_MINUTES));
        Cache::put($this->key($scope, $email).':cooldown', time() + self::COOLDOWN_SECONDS, now()->addSeconds(self::COOLDOWN_SECONDS));

        Mail::to($email)->send(new ResetPasswordCode($code, self::TTL_MINUTES, $audience));
    }

    /** Vrai si le code est bon (et le consomme). Trop d'essais invalident le code. */
    public function consume(string $scope, string $email, string $code): bool
    {
        $key = $this->key($scope, $email);
        $entry = Cache::get($key);
        if (! $entry) {
            return false;
        }
        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }
        if (hash_equals($entry['hash'], $this->hash($scope, $email, trim($code)))) {
            Cache::forget($key);

            return true;
        }
        $entry['attempts']++;
        Cache::put($key, $entry, now()->addMinutes(self::TTL_MINUTES));

        return false;
    }
}
