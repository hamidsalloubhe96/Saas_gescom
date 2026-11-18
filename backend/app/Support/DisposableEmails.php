<?php

namespace App\Support;

/** Domaines d'adresses e-mail jetables les plus courants, refusés à l'inscription. À compléter au besoin. */
class DisposableEmails
{
    public const DOMAINS = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org', 'sharklasers.com', 'grr.la', '10minutemail.com',
        '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'throwawaymail.com', 'yopmail.com', 'yopmail.fr', 'yopmail.net',
        'trashmail.com', 'trashmail.net', 'getnada.com', 'nada.email', 'maildrop.cc', 'dispostable.com', 'fakeinbox.com', 'mailnesia.com',
        'mintemail.com', 'mytemp.email', 'tempinbox.com', 'emailondeck.com', 'spamgourmet.com', 'mohmal.com', 'moakt.com', 'burnermail.io',
        'tmpmail.org', 'tmpmail.net', 'discard.email', 'anonbox.net', 'mailcatch.com', 'mail-temporaire.fr', 'jetable.org', 'getairmail.com',
    ];

    public static function is(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        return $domain !== '' && in_array($domain, self::DOMAINS, true);
    }
}
