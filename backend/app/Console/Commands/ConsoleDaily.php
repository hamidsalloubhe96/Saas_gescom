<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\SubscriptionService;
use App\Services\TaskRunner;
use App\Services\TenantExporter;
use App\Services\TenantLifecycle;
use Illuminate\Console\Command;

class ConsoleDaily extends Command
{
    protected $signature = 'console:daily';

    protected $description = "Tâches quotidiennes de la plateforme (abonnements, nettoyage, sauvegarde). Chaque tâche est isolée : si l'une échoue, les suivantes tournent quand même.";

    public function handle(TaskRunner $runner, SubscriptionService $subscriptions, TenantLifecycle $lifecycle, TenantExporter $exporter): int
    {
        $tasks = [
            'abonnements' => function () use ($subscriptions) {
                $r = $subscriptions->refresh();

                return "{$r['past_due']} en retard, {$r['expired']} expiré(s)";
            },
            'entreprises-abandonnees' => function () use ($lifecycle) {
                $r = $lifecycle->flagAbandoned();

                return "{$r['flagged']} suppression(s) programmée(s), {$r['failed']} échec(s)";
            },
            'purge-entreprises' => function () use ($lifecycle) {
                $r = $lifecycle->purgeDue();

                return "{$r['purged']} supprimée(s), {$r['failed']} échec(s)";
            },
            'nettoyage-journal' => function () {
                $n = AuditLog::where('created_at', '<', now()->subDays((int) config('console.audit_retention_days', 365)))->delete();

                return "{$n} entrée(s) supprimée(s)";
            },
            'taches-complementaires' => fn () => $this->call('console:extra-tasks') === 0 ? 'ok' : 'échec',
        ];

        // Sauvegarde complète de la base : seulement si activée (BACKUP_ENABLED=true).
        // Sur Render, la base managée a déjà ses propres sauvegardes.
        if (env('BACKUP_ENABLED', false)) {
            $tasks['sauvegarde-base'] = fn () => $exporter->backupAll();
        }

        $failed = 0;
        foreach ($tasks as $name => $task) {
            $run = $runner->run($name, $task);
            $this->line(($run->status === 'ok' ? 'OK    ' : 'ECHEC ')."{$name} : {$run->message}");
            $failed += $run->status === 'ok' ? 0 : 1;
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
