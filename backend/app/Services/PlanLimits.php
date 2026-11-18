<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Limites d'usage d'une entreprise (utilisateurs, produits, factures par mois) :
 * celles de son forfait, ou des limites personnalisées fixées par la console. À la limite, on refuse les CRÉATIONS ;
 * les données existantes ne sont jamais touchées.
 */
class PlanLimits
{
    public const RESOURCES = [
        'users' => 'utilisateurs', 'products' => 'produits', 'invoices_month' => 'factures par mois', 'branches' => 'points de vente',
    ];

    /** @return array{users: ?int, products: ?int, invoices_month: ?int, branches: ?int} NULL = illimité */
    public function limitsFor(Tenant $tenant): array
    {
        $sub = Subscription::with('plan')->firstWhere('tenant_id', $tenant->id);
        $plan = $sub?->plan;
        $custom = $sub?->custom_limits ?? [];

        $out = [];
        foreach (array_keys(self::RESOURCES) as $key) {
            $planValue = $plan?->{$key === 'invoices_month' ? 'max_invoices_month' : "max_{$key}"};
            $out[$key] = array_key_exists($key, $custom) ? ($custom[$key] === null ? null : (int) $custom[$key]) : $planValue;
        }

        return $out;
    }

    /** Consommation actuelle, mesurée DANS le schéma de l'entreprise courante (à appeler en contexte d'entreprise). */
    public function usageInContext(): array
    {
        return [
            'users' => DB::table('users')->where('is_active', true)->count(),
            'products' => DB::table('products')->count(),
            // Le siège ne compte pas : seuls les points de vente créés sont limitées.
            'branches' => DB::table('branches')->where('is_main', false)->where('is_active', true)->count(),
            'invoices_month' => DB::table('sales_documents')->where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'paid'])
                ->where('issue_date', '>=', now()->startOfMonth()->toDateString())->count(),
        ];
    }

    public function usageFor(Tenant $tenant): array
    {
        return $tenant->run(fn () => $this->usageInContext());
    }

    /** Palier d'usage : normal, alerte (80 %), critique (95 %), atteint (100 %). NULL si illimité. */
    public static function tier(?int $limit, int $used): ?string
    {
        if ($limit === null) {
            return null;
        }
        if ($limit <= 0) {
            return 'reached';
        }
        $pct = $used / $limit * 100;

        return $pct >= 100 ? 'reached' : ($pct >= 95 ? 'critical' : ($pct >= 80 ? 'warning' : 'normal'));
    }

    /**
     * Refuse l'ajout si la limite serait dépassée. À appeler dans une requête d'entreprise, avant la création.
     * $resource : users | products | invoices_month | branches.
     */
    public function ensureCanAdd(string $resource, int $adding = 1): void
    {
        $tenant = tenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $sub = Subscription::firstWhere('tenant_id', $tenant->id);
        if ($sub?->limitsUnlocked()) {
            return; // déblocage temporaire décidé par la console
        }

        $limit = $this->limitsFor($tenant)[$resource] ?? null;
        if ($limit === null) {
            return;
        }

        $used = $this->usageInContext()[$resource] ?? 0;
        if ($used + $adding > $limit) {
            abort(response()->json([
                'message' => "Limite de votre forfait atteinte : {$limit} ".self::RESOURCES[$resource].'. Passez à un forfait supérieur ou contactez la plateforme.',
                'code' => 'plan_limit',
                'resource' => $resource,
                'limit' => $limit,
            ], 422));
        }
    }
}
