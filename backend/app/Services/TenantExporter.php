<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Export et restauration des données d'UNE entreprise (son schéma PostgreSQL) avec pg_dump / pg_restore.
 * Les exports sont déposés sur le disque configuré (console.backup_disk).
 */
class TenantExporter
{
    public function disk()
    {
        return Storage::disk(config('console.backup_disk', 'local'));
    }

    public function schemaFor(Tenant|string $tenant): string
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return config('tenancy.database.prefix').$id.config('tenancy.database.suffix');
    }

    /** Chemin d'un outil PostgreSQL : dans console.pg_bin s'il est défini, sinon on suppose qu'il est dans le PATH. */
    public function binary(string $name): string
    {
        $dir = config('console.pg_bin');
        if (! $dir) {
            return $name;
        }

        $path = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$name;

        return PHP_OS_FAMILY === 'Windows' ? $path.'.exe' : $path;
    }

    /** Paramètres de connexion de la base commune, y compris quand l'application utilise DB_URL (Render). */
    public function connection(): array
    {
        $cfg = config('database.connections.'.config('tenancy.database.central_connection'));
        if (! empty($cfg['url'])) {
            $u = parse_url($cfg['url']);
            parse_str($u['query'] ?? '', $q);

            return [
                'host' => $u['host'] ?? null, 'port' => $u['port'] ?? 5432, 'database' => ltrim($u['path'] ?? '', '/'),
                'username' => isset($u['user']) ? urldecode($u['user']) : null, 'password' => isset($u['pass']) ? urldecode($u['pass']) : null,
                'sslmode' => $q['sslmode'] ?? ($cfg['sslmode'] ?? 'prefer'),
            ];
        }

        return [
            'host' => $cfg['host'], 'port' => $cfg['port'] ?? 5432, 'database' => $cfg['database'],
            'username' => $cfg['username'], 'password' => $cfg['password'] ?? null, 'sslmode' => $cfg['sslmode'] ?? 'prefer',
        ];
    }

    private function baseArgs(string $binary): array
    {
        $c = $this->connection();

        return [$this->binary($binary), '--host', $c['host'], '--port', (string) $c['port'], '--username', $c['username'], '--dbname', $c['database']];
    }

    /** Le mot de passe passe par l'environnement (PGPASSWORD), jamais par la ligne de commande. */
    private function env(): array
    {
        $c = $this->connection();

        return array_filter(['PGPASSWORD' => $c['password'], 'PGSSLMODE' => $c['sslmode']], fn ($v) => $v !== null && $v !== '');
    }

    /** @return string chemin relatif de l'export sur le disque de sauvegarde */
    public function export(Tenant $tenant): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gc_export_');

        try {
            $result = Process::env($this->env())->timeout(900)->run([
                ...$this->baseArgs('pg_dump'), '--schema', $this->schemaFor($tenant), '--no-owner', '--no-privileges', '--format=custom', '--file', $tmp,
            ]);

            if ($result->failed() || ! is_file($tmp) || filesize($tmp) === 0) {
                throw new RuntimeException('Export impossible : '.trim(mb_substr($result->errorOutput() ?: 'pg_dump a échoué', 0, 300)));
            }

            $path = "exports/{$tenant->id}/{$tenant->id}-".now()->format('Ymd-His').'.dump';
            $stream = fopen($tmp, 'r');
            $this->disk()->put($path, $stream);
            is_resource($stream) && fclose($stream);

            return $path;
        } finally {
            @unlink($tmp);
        }
    }

    /** Sauvegarde complète de la base (toutes les entreprises + base commune). */
    public function backupAll(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gc_backup_');

        try {
            $result = Process::env($this->env())->timeout(1800)->run([
                ...$this->baseArgs('pg_dump'), '--no-owner', '--no-privileges', '--format=custom', '--file', $tmp,
            ]);
            if ($result->failed() || filesize($tmp) === 0) {
                throw new RuntimeException('Sauvegarde impossible : '.trim(mb_substr($result->errorOutput() ?: 'pg_dump a échoué', 0, 300)));
            }

            $path = 'backups/database-'.now()->format('Ymd-His').'.dump';
            $stream = fopen($tmp, 'r');
            $this->disk()->put($path, $stream);
            is_resource($stream) && fclose($stream);

            // Rétention : on supprime les sauvegardes plus anciennes que N jours.
            $limit = now()->subDays((int) config('console.backup_retention_days', 14))->getTimestamp();
            foreach ($this->disk()->files('backups') as $f) {
                if ($this->disk()->lastModified($f) < $limit) {
                    $this->disk()->delete($f);
                }
            }

            return $path;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Restaure l'export d'une entreprise : le schéma existant est REMPLACÉ par le contenu de l'export.
     */
    public function restore(Tenant $tenant, string $path): void
    {
        abort_unless($this->disk()->exists($path), 404, 'Export introuvable.');

        $tmp = tempnam(sys_get_temp_dir(), 'gc_restore_');
        try {
            file_put_contents($tmp, $this->disk()->get($path));

            $result = Process::env($this->env())->timeout(1800)->run([
                ...$this->baseArgs('pg_restore'), '--clean', '--if-exists', '--no-owner', '--no-privileges', '--schema', $this->schemaFor($tenant), $tmp,
            ]);

            // pg_restore peut signaler des avertissements sans que la restauration échoue : on regarde le code de sortie.
            if ($result->exitCode() > 1) {
                throw new RuntimeException('Restauration impossible : '.trim(mb_substr($result->errorOutput(), 0, 300)));
            }
        } finally {
            @unlink($tmp);
        }
    }

    /** @return array<int, array{path: string, name: string, size: int, at: \Illuminate\Support\Carbon}> */
    public function list(Tenant|string $tenant): array
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return collect($this->disk()->files("exports/{$id}"))->map(fn ($f) => [
            'path' => $f, 'name' => basename($f), 'size' => $this->disk()->size($f), 'at' => \Illuminate\Support\Carbon::createFromTimestamp($this->disk()->lastModified($f)),
        ])->sortByDesc('at')->values()->all();
    }
}
