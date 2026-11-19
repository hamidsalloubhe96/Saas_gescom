<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MaintenanceNote;
use App\Models\TaskRun;
use App\Models\Tenant;
use App\Services\ErrorAlert;
use App\Services\TenantExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Santé de l'application : contrôles automatiques, tâches planifiées, rappels, migrations, sauvegardes (super administrateur). */
class HealthController extends Controller
{
    /** Tâches quotidiennes attendues : si la dernière exécution date de plus de 26 h, c'est anormal. */
    private const EXPECTED_DAILY = ['abonnements'];

    public function index(TenantExporter $exporter)
    {
        $tenantFiles = count(glob(database_path('migrations/tenant/*.php')) ?: []);

        return view('console.health.index', [
            'system' => $this->system(),
            'warnings' => $this->warnings(),
            'lastRuns' => TaskRun::latest('ran_at')->limit(40)->get()->groupBy('name')->map->first(),
            'runs' => TaskRun::latest('id')->limit(15)->get(),
            'taskDelayed' => $this->taskDelayed(),
            'reminders' => $this->reminders(),
            'migrations' => $this->migrations($tenantFiles),
            'migrationFiles' => $tenantFiles,
            'backups' => $this->safeList(fn () => collect($exporter->disk()->files('backups'))->map(fn ($f) => ['path' => $f, 'name' => basename($f), 'size' => $exporter->disk()->size($f), 'at' => \Illuminate\Support\Carbon::createFromTimestamp($exporter->disk()->lastModified($f))])->sortByDesc('at')->values()->all()),
            'alertEnabled' => app(ErrorAlert::class)->enabled(),
        ]);
    }

    private function safeList(callable $fn): array
    {
        try {
            return $fn();
        } catch (Throwable) {
            return [];
        }
    }

    private function system(): array
    {
        $out = ['db_ms' => null, 'db_size' => null, 'schemas' => null, 'failed_jobs' => null, 'pending_jobs' => null, 'disk_free' => null];

        try {
            $t = hrtime(true);
            DB::select('select 1');
            $out['db_ms'] = round((hrtime(true) - $t) / 1_000_000, 1);

            if (DB::getDriverName() === 'pgsql') {
                $out['db_size'] = (int) DB::selectOne('select pg_database_size(current_database()) as s')->s;
                $out['schemas'] = (int) DB::selectOne("select count(*) as c from pg_namespace where nspname like ?", [config('tenancy.database.prefix').'%'])->c;
            }
            $out['failed_jobs'] = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
            $out['pending_jobs'] = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
        } catch (Throwable $e) {
            report($e);
        }
        $out['disk_free'] = @disk_free_space(storage_path()) ?: null;

        return $out + ['php' => PHP_VERSION, 'laravel' => app()->version(), 'env' => app()->environment(), 'debug' => (bool) config('app.debug')];
    }

    /** Points d'attention de configuration (surtout en production). */
    private function warnings(): array
    {
        $w = [];
        $prod = app()->isProduction();

        if ($prod && config('app.debug')) {
            $w[] = ['bad', 'APP_DEBUG est activé en production : les erreurs affichent des détails internes. Désactivez-le.'];
        }
        if ($prod && in_array(config('mail.default'), ['log', 'array'], true)) {
            $w[] = ['bad', 'Aucun envoi d\'e-mail réel n\'est configuré (MAIL_MAILER=log) : accès fondateurs, alertes et relances ne partiront pas.'];
        }
        if (! config('console.alert_email')) {
            $w[] = ['warn', 'Aucune adresse d\'alerte (CONSOLE_ALERT_EMAIL) : vous ne serez pas prévenu par e-mail en cas d\'erreur.'];
        }
        if ($prod && config('console.backup_disk') === 'local') {
            $w[] = ['warn', 'Les exports et sauvegardes sont écrits sur le disque du conteneur, qui est EFFACÉ à chaque déploiement. Configurez BACKUP_DISK sur un stockage externe (ex. S3) pour les conserver.'];
        }
        if (! env('BACKUP_ENABLED', false)) {
            $w[] = ['info', 'La sauvegarde complète quotidienne de la base est désactivée (BACKUP_ENABLED). Sur Render, la base managée a ses propres sauvegardes.'];
        }

        return $w;
    }

    private function taskDelayed(): array
    {
        $late = [];
        foreach (self::EXPECTED_DAILY as $name) {
            $last = TaskRun::where('name', $name)->latest('ran_at')->first();
            if (! $last || $last->ran_at->lt(now()->subHours(26))) {
                $late[] = $name;
            }
        }

        return $late;
    }

    private function reminders(): array
    {
        $notes = MaintenanceNote::all()->keyBy('key');

        return collect(MaintenanceNote::REMINDERS)->map(function ($def, $key) use ($notes) {
            $done = $notes[$key]->done_at ?? null;

            return ['key' => $key, 'label' => $def[0], 'every' => $def[1], 'done_at' => $done, 'due' => ! $done || $done->lt(now()->subDays($def[1]))];
        })->values()->all();
    }

    /** Migrations en attente par entreprise (comparées aux fichiers du dépôt). */
    private function migrations(int $files): array
    {
        $rows = [];
        foreach (Tenant::orderBy('id')->get() as $tenant) {
            try {
                $applied = $tenant->run(fn () => DB::table('migrations')->count());
                $rows[] = ['tenant' => $tenant, 'applied' => $applied, 'pending' => max(0, $files - $applied), 'error' => null];
            } catch (Throwable $e) {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                $rows[] = ['tenant' => $tenant, 'applied' => null, 'pending' => null, 'error' => 'Schéma illisible'];
            }
        }

        return $rows;
    }

    public function note(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'in:'.implode(',', array_keys(MaintenanceNote::REMINDERS))]]);
        MaintenanceNote::updateOrCreate(['key' => $data['key']], ['done_at' => now(), 'done_by' => $request->user('admin')->id]);
        AuditLog::record('health.note', 'Noté comme fait : '.MaintenanceNote::REMINDERS[$data['key']][0]);

        return back()->with('success', 'Noté comme fait.');
    }

    public function testError(ErrorAlert $alert): RedirectResponse
    {
        if (! $alert->enabled()) {
            return back()->withErrors(['health' => 'Aucune adresse d\'alerte configurée (CONSOLE_ALERT_EMAIL) : impossible d\'envoyer le test.']);
        }
        $sent = $alert->notify(new \RuntimeException('Ceci est un TEST d\'alerte d\'erreur envoyé depuis la console.'), 'Test depuis la console', force: true);
        AuditLog::record('health.test_error', 'Test de l\'alerte d\'erreurs');

        return $sent ? back()->with('success', 'Alerte de test envoyée à '.config('console.alert_email').'.')
            : back()->withErrors(['health' => 'L\'envoi a échoué : vérifiez la configuration e-mail (voir les journaux du serveur).']);
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $to = config('console.alert_email') ?: $request->user('admin')->email;

        try {
            Mail::raw('Ceci est un e-mail de test envoyé depuis la console Salloubhe Gescom. Si vous le recevez, l\'envoi fonctionne.', fn ($m) => $m->to($to)->subject('[Salloubhe Gescom] Test d\'envoi d\'e-mail'));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['health' => 'Envoi impossible : '.mb_substr($e->getMessage(), 0, 200)]);
        }
        AuditLog::record('health.test_email', "E-mail de test envoyé à {$to}");

        return back()->with('success', "E-mail de test envoyé à {$to}.".(config('mail.default') === 'log' ? ' (MAIL_MAILER=log : il est écrit dans les journaux, pas envoyé.)' : ''));
    }

    public function migrateTenant(Tenant $tenant): RedirectResponse
    {
        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
        AuditLog::record('health.migrate', "Migrations appliquées à {$tenant->name}", $tenant->id);

        return back()->with('success', "Migrations appliquées à « {$tenant->name} ».");
    }

    /** Toutes les entreprises, en arrière-plan : la requête n'attend pas (le résultat se voit en actualisant la page). */
    public function migrateAll(): RedirectResponse
    {
        Process::path(base_path())->start([PHP_BINARY, 'artisan', 'tenants:migrate', '--force']);
        AuditLog::record('health.migrate_all', 'Migrations de toutes les entreprises lancées en arrière-plan');

        return back()->with('success', 'Migrations lancées en arrière-plan. Actualisez cette page dans un instant pour voir l\'avancement.');
    }

    public function backupNow(TenantExporter $exporter): RedirectResponse
    {
        try {
            $path = $exporter->backupAll();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['health' => $e->getMessage()]);
        }
        AuditLog::record('health.backup', "Sauvegarde de la base créée : {$path}");

        return back()->with('success', "Sauvegarde créée : {$path}");
    }

    /** Téléchargement d'un export ou d'une sauvegarde (chemin strictement limité aux dossiers exports/ et backups/). */
    public function download(Request $request, TenantExporter $exporter): StreamedResponse
    {
        $path = (string) $request->query('path');
        abort_unless(preg_match('#^(exports|backups)/[A-Za-z0-9_./-]+\.dump$#', $path) && ! str_contains($path, '..'), 404);
        abort_unless($exporter->disk()->exists($path), 404, 'Fichier introuvable.');

        AuditLog::record('health.download', "Téléchargement de {$path}", preg_match('#^exports/([^/]+)/#', $path, $m) ? $m[1] : null);

        return $exporter->disk()->download($path);
    }
}
