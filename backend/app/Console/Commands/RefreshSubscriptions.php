<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class RefreshSubscriptions extends Command
{
    protected $signature = 'subscriptions:refresh';

    protected $description = 'Fait avancer les abonnements selon les dates : essai terminé, échéance dépassée (jours de grâce), expiration';

    public function handle(SubscriptionService $subscriptions): int
    {
        $r = $subscriptions->refresh();
        $this->info("Abonnements : {$r['past_due']} passé(s) en retard, {$r['expired']} expiré(s) et suspendu(s).");

        return self::SUCCESS;
    }
}
