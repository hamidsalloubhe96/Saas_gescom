<?php

namespace App\Console\Commands;

use App\Services\TenantExporter;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Sauvegarde complète de la base (pg_dump) sur le disque de sauvegarde configuré';

    public function handle(TenantExporter $exporter): int
    {
        try {
            $this->info('Sauvegarde créée : '.$exporter->backupAll());
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
