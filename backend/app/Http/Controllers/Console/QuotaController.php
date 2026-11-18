<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/** Limites d'usage de toutes les entreprises : qui approche ou atteint son plafond. */
class QuotaController extends Controller
{
    private const TIER_ORDER = ['reached' => 0, 'critical' => 1, 'warning' => 2, 'normal' => 3];

    public function index(Request $request, PlanLimits $limits)
    {
        $request->validate(['tier' => ['nullable', 'in:reached,critical,warning,normal']]);

        $rows = [];
        foreach (Tenant::with('subscription.plan')->orderBy('id')->get() as $tenant) {
            try {
                $usage = $limits->usageFor($tenant);
            } catch (Throwable $e) {
                report($e);
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                $usage = ['users' => 0, 'products' => 0, 'invoices_month' => 0, 'branches' => 0];
            }

            $lim = $limits->limitsFor($tenant);
            $tiers = collect(PlanLimits::RESOURCES)->keys()->mapWithKeys(fn ($k) => [$k => PlanLimits::tier($lim[$k], $usage[$k])]);
            $worst = $tiers->filter()->sortBy(fn ($t) => self::TIER_ORDER[$t])->first() ?? 'normal';

            $rows[] = compact('tenant', 'usage', 'lim', 'tiers', 'worst') + ['sub' => $tenant->subscription];
        }

        $rows = collect($rows);
        $counts = $rows->countBy('worst');
        if ($tier = $request->query('tier')) {
            $rows = $rows->where('worst', $tier);
        }

        return view('console.quotas.index', [
            'rows' => $rows->sortBy(fn ($r) => self::TIER_ORDER[$r['worst']])->values(),
            'counts' => $counts,
            'resources' => PlanLimits::RESOURCES,
        ]);
    }

    /** Limites personnalisées pour une entreprise (vide = celles du forfait). */
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'users' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'products' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'invoices_month' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'branches' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'unlimited' => ['nullable', 'array'],
            'unlimited.*' => ['in:users,products,invoices_month,branches'],
        ]);

        $custom = [];
        foreach (array_keys(PlanLimits::RESOURCES) as $key) {
            if (in_array($key, $data['unlimited'] ?? [], true)) {
                $custom[$key] = null; // illimité pour cette entreprise
            } elseif (isset($data[$key])) {
                $custom[$key] = (int) $data[$key];
            }
        }

        $sub = Subscription::where('tenant_id', $tenant->id)->firstOrFail();
        $sub->update(['custom_limits' => $custom ?: null]);

        AuditLog::record('quota.update', "Limites personnalisées pour {$tenant->name}", $tenant->id, ['limites' => $custom]);

        return back()->with('success', $custom ? 'Limites personnalisées enregistrées.' : 'Limites personnalisées retirées : celles du forfait s\'appliquent.');
    }

    public function unlock(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:90']]);
        Subscription::where('tenant_id', $tenant->id)->firstOrFail()->update(['limits_unlocked_until' => now()->addDays((int) $data['days'])]);

        AuditLog::record('quota.unlock', "Limites débloquées {$data['days']} jour(s) pour {$tenant->name}", $tenant->id, ['days' => $data['days']]);

        return back()->with('success', "Limites débloquées pour {$data['days']} jour(s) : l'entreprise peut continuer à créer le temps de changer de forfait.");
    }

    public function relock(Tenant $tenant): RedirectResponse
    {
        Subscription::where('tenant_id', $tenant->id)->firstOrFail()->update(['limits_unlocked_until' => null]);
        AuditLog::record('quota.relock', "Limites rétablies pour {$tenant->name}", $tenant->id);

        return back()->with('success', 'Limites rétablies.');
    }
}
