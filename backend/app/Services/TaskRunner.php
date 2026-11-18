<?php

namespace App\Services;

use App\Models\TaskRun;
use Throwable;

/**
 * Exécute une tâche planifiée de façon ISOLÉE : si elle échoue, l'erreur est consignée et les suivantes tournent quand même.
 * Le résultat (ok / échec, durée, message) est visible sur la page Santé de la console.
 */
class TaskRunner
{
    public function run(string $name, callable $task): TaskRun
    {
        $start = hrtime(true);

        try {
            $message = (string) $task();
            $status = 'ok';
        } catch (Throwable $e) {
            report($e);
            $status = 'failed';
            $message = $e->getMessage();
            // Un échec de tâche planifiée est ce qu'on veut apprendre tout de suite : alerte par e-mail si configurée.
            app(ErrorAlert::class)->notify($e, "Tâche planifiée « {$name} »");
        }

        return TaskRun::create([
            'name' => $name, 'status' => $status, 'message' => mb_substr($message, 0, 500),
            'duration_ms' => (int) ((hrtime(true) - $start) / 1_000_000), 'ran_at' => now(),
        ]);
    }
}
