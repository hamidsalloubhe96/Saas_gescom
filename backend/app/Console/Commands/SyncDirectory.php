<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncDirectory extends Command
{
    protected $signature = 'console:sync-directory {--tenant= : Limiter à une entreprise}';

    protected $description = 'Reconstruit l\'annuaire central des utilisateurs depuis chaque entreprise (rattrapage / réparation)';

    public function handle(): int
    {
        $tenants = $this->option('tenant') ? Tenant::whereKey($this->option('tenant'))->get() : Tenant::all();
        $total = 0;

        foreach ($tenants as $tenant) {
            try {
                $users = $tenant->run(fn () => DB::table('users')->get(['id', 'name', 'email', 'role', 'is_active']));
            } catch (\Throwable $e) {
                $this->error("{$tenant->id} : {$e->getMessage()}");
                if (tenancy()->initialized) {
                    tenancy()->end();
                }

                continue;
            }

            $ids = [];
            foreach ($users as $u) {
                TenantUser::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => $u->id],
                    ['name' => $u->name, 'email' => strtolower($u->email), 'role' => $u->role, 'is_active' => (bool) $u->is_active],
                );
                $ids[] = $u->id;
            }
            // Supprime les entrées dont l'utilisateur n'existe plus.
            TenantUser::where('tenant_id', $tenant->id)->whereNotIn('user_id', $ids)->delete();

            $total += count($ids);
            $this->line("{$tenant->id} : ".count($ids).' utilisateur(s)');
        }

        $this->info("Annuaire synchronisé : {$total} utilisateur(s).");

        return self::SUCCESS;
    }
}
