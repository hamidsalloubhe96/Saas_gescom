<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Alerte par e-mail à l'équipe quand l'application rencontre une erreur grave.
 * Limitée (une alerte identique toutes les 10 minutes) pour ne pas noyer la boîte mail ; ne casse jamais la requête.
 */
class ErrorAlert
{
    public function enabled(): bool
    {
        return (bool) config('console.alert_email');
    }

    public function notify(Throwable $e, ?string $context = null, bool $force = false): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $key = 'error_alert:'.md5(get_class($e).$e->getFile().$e->getLine());
        if (! $force && ! Cache::add($key, 1, 600)) {
            return false; // déjà alertée récemment
        }

        try {
            $body = ($context ? "{$context}\n\n" : '').get_class($e).': '.$e->getMessage()."\n"
                .$e->getFile().':'.$e->getLine()."\n\n".'Application : '.config('app.name').' ('.config('app.url').")\n".'Date : '.now()->toDateTimeString();
            Mail::raw($body, fn ($m) => $m->to(config('console.alert_email'))->subject('[Salloubhe Gescom] Erreur : '.mb_substr($e->getMessage(), 0, 80)));

            return true;
        } catch (Throwable $mailError) {
            // Dernier recours : si l'envoi échoue, une ligne dans les journaux du serveur.
            error_log('Salloubhe Gescom : alerte d\'erreur non envoyée ('.$mailError->getMessage().') pour : '.$e->getMessage());

            return false;
        }
    }
}
