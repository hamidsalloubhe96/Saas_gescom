<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Review;
use App\Models\SignupRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    private const PERIODS = ['7' => '7 derniers jours', '30' => '30 derniers jours', '90' => '90 derniers jours', 'month' => 'Ce mois-ci', 'year' => 'Cette année'];

    public function __invoke(Request $request)
    {
        $request->validate(['period' => ['nullable', 'in:'.implode(',', array_keys(self::PERIODS))], 'plan' => ['nullable', 'integer'], 'country' => ['nullable', 'string', 'max:60']]);

        $admin = $request->user('admin');
        $period = $request->query('period', '30');
        $from = match ($period) {
            '7' => now()->subDays(7), '90' => now()->subDays(90), 'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(), default => now()->subDays(30),
        };
        $planId = $request->integer('plan') ?: null;
        $country = $request->query('country') ?: null;

        // Périmètre : les entreprises correspondant aux filtres Forfait et Pays.
        $scope = Tenant::query()
            ->when($country, fn ($q) => $q->where('country', $country))
            ->when($planId, fn ($q) => $q->whereHas('subscription', fn ($s) => $s->where('plan_id', $planId)));
        $scopeIds = (clone $scope)->pluck('id');

        $counts = (clone $scope)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        // Encaissements validés sur la période.
        $payments = SubscriptionPayment::where('status', 'validated')->where('validated_at', '>=', $from)->whereIn('tenant_id', $scopeIds)->get(['amount', 'currency', 'validated_at']);
        $revenue = (float) $payments->sum('amount');
        $byDay = $from->diffInDays(now()) > 62 ? 'Y-m' : 'Y-m-d';
        $series = $payments->groupBy(fn ($p) => $p->validated_at->format($byDay))->map(fn ($g) => (float) $g->sum('amount'))->sortKeys();

        // Revenu récurrent mensuel : prix mensualisé des abonnements actifs payants.
        $paying = Subscription::with('plan')->where('status', 'active')->whereIn('tenant_id', $scopeIds)->get()->filter(fn ($s) => (float) $s->plan->price > 0);
        $mrr = (float) $paying->sum(fn ($s) => (float) $s->plan->price / max(1, $s->plan->period_months));
        $churned = Subscription::whereIn('status', ['expired', 'cancelled'])->whereNotNull('current_period_end')->where('updated_at', '>=', $from)->whereIn('tenant_id', $scopeIds)->count();

        return view('console.dashboard', [
            'period' => $period, 'periods' => self::PERIODS, 'planId' => $planId, 'country' => $country,
            'plans' => Plan::orderBy('sort')->get(['id', 'name']),
            'countries' => Tenant::whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
            'total' => $counts->sum(), 'counts' => $counts,
            'newInPeriod' => (clone $scope)->where('created_at', '>=', $from)->count(),
            'activeWeek' => (clone $scope)->where('status', 'active')->where('last_activity_at', '>=', now()->subDays(7))->count(),
            'revenue' => $revenue, 'currency' => $payments->first()?->currency ?? \App\Models\PlatformSetting::get('currency', 'XOF'),
            'series' => $series,
            'mrr' => $mrr, 'arr' => $mrr * 12, 'payingCount' => $paying->count(),
            'arpa' => $paying->count() ? $mrr / $paying->count() : 0,
            'churned' => $churned, 'churnRate' => ($paying->count() + $churned) > 0 ? $churned / ($paying->count() + $churned) * 100 : 0,
            'byPlan' => Subscription::selectRaw('plan_id, count(*) as c')->whereIn('tenant_id', $scopeIds)->groupBy('plan_id')->pluck('c', 'plan_id'),
            'planNames' => Plan::pluck('name', 'id'),
            'byCountry' => (clone $scope)->selectRaw("coalesce(country, 'Non renseigné') as k, count(*) as c")->groupBy('k')->orderByDesc('c')->pluck('c', 'k'),
            'upcoming' => Subscription::with(['tenant', 'plan'])->whereHas('tenant')->whereIn('status', ['trial', 'active', 'past_due'])
                ->orderByRaw('coalesce(current_period_end, trial_ends_at) asc')->limit(6)->get(),
            'recent' => Tenant::latest('created_at')->limit(5)->get(),
            'todo' => $this->todo(),
            'activity' => $admin->can('audit.view') ? AuditLog::latest('id')->limit(8)->get() : collect(),
        ]);
    }

    /** « À traiter » : ce qui demande une action, du plus pressant au moins pressant, chacun avec son lien. */
    private function todo(): array
    {
        $items = [];
        $add = function (int $n, string $singular, string $plural, string $url, string $tone = 'warn') use (&$items) {
            if ($n > 0) {
                $items[] = ['label' => $n.' '.($n > 1 ? $plural : $singular), 'url' => $url, 'tone' => $tone];
            }
        };

        $add(SubscriptionPayment::where('status', 'pending')->count(), 'paiement à valider', 'paiements à valider', route('console.payments.index'));
        $add(Subscription::where('status', 'past_due')->count(), 'abonnement en retard', 'abonnements en retard', route('console.dues.index', ['filter' => 'overdue']), 'bad');
        $add(Subscription::where('status', 'trial')->whereBetween('trial_ends_at', [now(), now()->addDays(3)])->count(), 'essai se termine dans 3 jours', 'essais se terminent dans 3 jours', route('console.dues.index', ['filter' => 'soon']));
        $add($this->quotaReached(), 'entreprise à 100 % d\'une limite', 'entreprises à 100 % d\'une limite', route('console.quotas.index', ['tier' => 'reached']));
        $add(Subscription::where('status', 'expired')->count(), 'abonnement expiré', 'abonnements expirés', route('console.dues.index', ['filter' => 'expired']), 'bad');
        $add(SignupRequest::where('status', 'new')->count(), 'demande d\'inscription', 'demandes d\'inscription', route('console.requests.index'), 'info');
        $add(Review::where('status', 'pending')->count(), 'avis à relire', 'avis à relire', route('console.reviews.index'), 'info');
        if (Schema::hasTable('support_tickets')) {
            $add(\App\Models\SupportTicket::whereIn('status', ['open'])->count(), 'ticket de support ouvert', 'tickets de support ouverts', route('console.tickets.index'), 'info');
        }

        return $items;
    }

    /** Nombre d'entreprises ayant atteint une limite (mesure par entreprise : mise en cache 10 min). */
    private function quotaReached(): int
    {
        return Cache::remember('console.dashboard.quota_reached', 600, function () {
            $limits = app(PlanLimits::class);
            $n = 0;
            foreach (Tenant::where('status', 'active')->limit(300)->get() as $tenant) {
                try {
                    $usage = $limits->usageFor($tenant);
                    $lim = $limits->limitsFor($tenant);
                    $n += collect(array_keys(PlanLimits::RESOURCES))->contains(fn ($k) => PlanLimits::tier($lim[$k], $usage[$k]) === 'reached') ? 1 : 0;
                } catch (\Throwable $e) {
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                }
            }

            return $n;
        });
    }
}
