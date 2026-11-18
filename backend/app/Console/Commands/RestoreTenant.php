<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\TenantExporter;
use Illuminate\Console\Command;

class RestoreTenant extends Command
{
    protected $signature = 'tenants:restore {tenant : Identifiant de l\'entreprise} {file? : Chemin de l\'export (sinon le plus récent)} {--force : Ne pas demander de confirmation}';

    protected $description = "Restaure UNE entreprise depuis un de ses exports : son schéma actuel est REMPLACÉ";

    public function handle(TenantExporter $exporter): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (! $tenant) {
            $this->error('Entreprise introuvable.');

            return self::FAILURE;
        }

        $file = $this->argument('file') ?: ($exporter->list($tenant)[0]['path'] ?? null);
        if (! $file) {
            $this->error('Aucun export disponible pour cette entreprise.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Remplacer les données actuelles de « {$tenant->name} » par l'export {$file} ?")) {
            return self::FAILURE;
        }

        $exporter->restore($tenant, $file);
        AuditLog::record('tenant.restore', "Entreprise « {$tenant->name} » restaurée depuis {$file}", $tenant->id, [], null);
        $this->info("Entreprise « {$tenant->name} » restaurée depuis {$file}.");

        return self::SUCCESS;
    }
}
