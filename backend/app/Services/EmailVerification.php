<?php

namespace App\Services;

use App\Mail\VerifyEmailCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/** Code à 6 chiffres envoyé par e-mail avant la création d'une entreprise (valable 15 min, 5 essais). */
class EmailVerification
{
    private const TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    private function key(string $email): string
    {
        return 'email_code:'.sha1(strtolower(trim($email)));
    }

    private function hash(string $email, string $code): string
    {
        return hash_hmac('sha256', strtolower(trim($email)).'|'.$code, (string) config('app.key'));
    }

    public function send(string $email): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->key($email), ['hash' => $this->hash($email, $code), 'attempts' => 0], now()->addMinutes(self::TTL_MINUTES));

        Mail::to($email)->send(new VerifyEmailCode($code, self::TTL_MINUTES));
    }

    /** Vrai si le code est bon ; il ne sert qu'une fois. Trop d'essais invalident le code. */
    public function check(string $email, string $code): bool
    {
        $entry = Cache::get($this->key($email));
        if (! $entry) {
            return false;
        }

        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($this->key($email));

            return false;
        }

        if (hash_equals($entry['hash'], $this->hash($email, trim($code)))) {
            Cache::forget($this->key($email));

            return true;
        }

        $entry['attempts']++;
        Cache::put($this->key($email), $entry, now()->addMinutes(self::TTL_MINUTES));

        return false;
    }
}
