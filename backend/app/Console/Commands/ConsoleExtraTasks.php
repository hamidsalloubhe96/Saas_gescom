<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionReminder;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Tâches quotidiennes complémentaires : relances d'abonnement (une seule fois par échéance). */
class ConsoleExtraTasks extends Command
{
    protected $signature = 'console:extra-tasks';

    protected $description = 'Relances d\'abonnement (échéance proche, abonnement dépassé)';

    public function handle(): int
    {
        $sent = 0;

        $expiring = Subscription::with('tenant')->whereIn('status', ['trial', 'active'])->get()
            ->filter(fn ($s) => $s->endsAt() && $s->endsAt()->isBetween(now(), now()->addDays(3)));
        $overdue = Subscription::with('tenant')->whereIn('status', ['past_due', 'expired'])->get()
            ->filter(fn ($s) => $s->endsAt() && $s->endsAt()->isPast() && $s->endsAt()->greaterThan(now()->subDays(30)));

        foreach ([['expiring', $expiring], ['overdue', $overdue]] as [$kind, $list]) {
            foreach ($list as $sub) {
                $tenant = $sub->tenant;
                if (! $tenant || ! $tenant->owner_email || $tenant->status === 'pending_deletion') {
                    continue;
                }
                $period = $sub->endsAt()->toDateString();
                // L'unicité en base garantit qu'une relance n'est envoyée qu'une fois par échéance, même en cas de double exécution.
                $first = DB::connection(config('tenancy.database.central_connection'))->table('reminders_sent')
                    ->insertOrIgnore(['tenant_id' => $tenant->id, 'kind' => $kind, 'period_key' => $period, 'sent_at' => now()]);
                if (! $first) {
                    continue;
                }
                Mail::to($tenant->owner_email)->send(new SubscriptionReminder($tenant->name, $kind, $sub->endsAt()->format('d/m/Y')));
                $sent++;
            }
        }

        $this->info("{$sent} relance(s) envoyée(s)");

        return self::SUCCESS;
    }
}
