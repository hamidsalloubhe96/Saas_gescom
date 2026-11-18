<?php

namespace App\Console\Commands;

use App\Services\TenantLifecycle;
use Illuminate\Console\Command;

class PurgeTenants extends Command
{
    protected $signature = 'tenants:purge';

    protected $description = 'Supprime définitivement les entreprises dont le délai de grâce est écoulé (jamais les protégées)';

    public function handle(TenantLifecycle $lifecycle): int
    {
        $r = $lifecycle->purgeDue();
        $this->info("{$r['purged']} entreprise(s) supprimée(s), {$r['failed']} échec(s).");

        return $r['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
