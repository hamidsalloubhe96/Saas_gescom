<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Données publiques de la vitrine : recherche d'entreprise (« Rechercher mon entreprise ») et forfaits. */
class PublicCompaniesController extends Controller
{
    /** Renvoie uniquement identifiant et nom des entreprises actives ; 2 caractères minimum, 6 résultats maximum. */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 60) {
            return response()->json([]);
        }

        $query = Tenant::where('status', Tenant::STATUS_ACTIVE);
        Search::contains($query, ['id', 'json:data.name'], $q);

        return response()->json(
            $query->orderBy('id')->limit(6)->get()->map(fn (Tenant $t) => ['slug' => $t->id, 'name' => $t->name ?? $t->id])->values(),
        );
    }

    public function plans(): JsonResponse
    {
        return response()->json(
            Plan::where('is_active', true)->where('is_trial', false)->orderBy('sort')
                ->get(['name', 'description', 'price', 'currency', 'period_months', 'max_users', 'max_products', 'max_invoices_month']),
        )->header('Cache-Control', 'public, max-age=300');
    }
}
