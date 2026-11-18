<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RegisterSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs de pilotage. « Chiffre d'affaires » = factures émises (hors brouillons et annulées),
 * ventes comptoir incluses, en TTC. Tout est calculé par la base de l'entreprise courante.
 */
class DashboardController extends Controller
{
    private const VALID = ['issued', 'partial', 'paid'];

    private function f(mixed $v): float
    {
        return round((float) $v, 2);
    }

    /** Factures « comptées » dans le chiffre d'affaires. */
    private function invoices()
    {
        return DB::table('sales_documents')->where('type', 'invoice')->whereIn('status', self::VALID)
            ->when($this->branch(), fn ($q, $b) => $q->where('branch_id', $b));
    }

    /** Point de vente choisi ou imposé (null = toute l'entreprise). */
    private function branch(): ?int
    {
        return app(\App\Services\CurrentBranch::class)->id();
    }

    public function __invoke(): JsonResponse
    {
        $branch = $this->branch();
        $restricted = app(\App\Services\CurrentBranch::class)->isRestricted();
        $now = now();
        $today = $now->toDateString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $prevSameDay = $now->copy()->subMonthNoOverflow()->toDateString(); // même point du mois précédent
        $since = $now->copy()->subDays(29)->toDateString();

        // --- Ventes ---
        $salesToday = $this->f($this->invoices()->where('issue_date', $today)->sum('total'));
        $salesMonth = $this->f($this->invoices()->whereBetween('issue_date', [$monthStart, $today])->sum('total'));
        $salesPrev = $this->f($this->invoices()->whereBetween('issue_date', [$prevStart, $prevSameDay])->sum('total'));
        $invoicesMonth = $this->invoices()->whereBetween('issue_date', [$monthStart, $today])->count();

        // --- Créances clients ---
        $open = $this->invoices()->whereIn('status', ['issued', 'partial'])->whereRaw('total > amount_paid');
        $receivable = $this->f((clone $open)->sum(DB::raw('total - amount_paid')));
        $overdue = (clone $open)->whereNotNull('due_date')->where('due_date', '<', $today);
        $overdueAmount = $this->f((clone $overdue)->sum(DB::raw('total - amount_paid')));
        $overdueCount = (clone $overdue)->count();

        // --- Dettes fournisseurs ---
        // Les achats sont gérés par le siège : un point de vente n'a ni dettes fournisseurs ni réceptions.
        $payableQuery = PurchaseOrder::whereIn('status', ['ordered', 'partial', 'received'])->whereRaw('total > amount_paid')->when($restricted, fn ($q) => $q->whereRaw('1 = 0'));
        $payable = $this->f((clone $payableQuery)->sum(DB::raw('total - amount_paid')));
        $payableCount = (clone $payableQuery)->count();

        // --- Stock ---
        if ($branch) {
            $stockValue = $this->f(DB::table('stock_levels as s')->join('products as p', 'p.id', '=', 's.product_id')->where('s.branch_id', $branch)->sum(DB::raw('s.quantity * p.purchase_price')));
            $lowQuery = Product::forBranch($branch)->where('min_stock', '>', 0)->where('is_active', true)
                ->whereRaw('coalesce((select quantity from stock_levels where stock_levels.product_id = products.id and stock_levels.branch_id = ?), 0) <= products.min_stock', [$branch]);
            $lowCount = (clone $lowQuery)->count();
            $lowStock = (clone $lowQuery)->orderByRaw('coalesce((select quantity from stock_levels where stock_levels.product_id = products.id and stock_levels.branch_id = ?), 0)', [$branch])->limit(6)->get()->each->applyBranch()
                ->map(fn ($p) => $p->only(['id', 'name', 'sku', 'unit', 'stock_quantity', 'min_stock']));
        } else {
            $stockValue = $this->f(Product::sum(DB::raw('stock_quantity * purchase_price')));
            $lowQuery = Product::where('min_stock', '>', 0)->whereColumn('stock_quantity', '<=', 'min_stock')->where('is_active', true);
            $lowCount = (clone $lowQuery)->count();
            $lowStock = (clone $lowQuery)->orderBy('stock_quantity')->limit(6)
                ->get(['id', 'name', 'sku', 'unit', 'stock_quantity', 'min_stock']);
        }

        // --- À traiter ---
        $quotes = DB::table('sales_documents')->where('type', 'quote')->whereIn('status', ['draft', 'sent'])->when($branch, fn ($q, $b) => $q->where('branch_id', $b));
        $awaiting = PurchaseOrder::whereIn('status', ['ordered', 'partial'])->when($restricted, fn ($q) => $q->whereRaw('1 = 0'));

        // --- Courbe des 30 derniers jours (jours sans vente = 0) ---
        $byDay = $this->invoices()->whereBetween('issue_date', [$since, $today])
            ->selectRaw('issue_date as d, sum(total) as t, count(*) as n')->groupBy('issue_date')->get()->keyBy('d');
        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = $now->copy()->subDays($i)->toDateString();
            $series[] = ['date' => $d, 'total' => $this->f($byDay[$d]->t ?? 0), 'count' => (int) ($byDay[$d]->n ?? 0)];
        }

        // --- Meilleurs produits (30 jours, CA HT) ---
        $top = DB::table('sales_document_lines as l')
            ->join('sales_documents as d', 'd.id', '=', 'l.sales_document_id')
            ->where('d.type', 'invoice')->whereIn('d.status', self::VALID)
            ->when($branch, fn ($q, $b) => $q->where('d.branch_id', $b))
            ->whereBetween('d.issue_date', [$since, $today])
            ->groupBy('l.product_id', 'l.description')
            ->selectRaw('l.product_id, l.description as name, sum(l.quantity) as quantity, sum(l.line_total) as revenue')
            ->orderByDesc('revenue')->limit(5)->get()
            ->map(fn ($r) => ['product_id' => $r->product_id, 'name' => $r->name, 'quantity' => (float) $r->quantity, 'revenue' => $this->f($r->revenue)]);

        $register = RegisterSession::whereNull('closed_at')->first(['id', 'opened_at']);

        return response()->json([
            'sales' => [
                'today' => $salesToday,
                'month' => $salesMonth,
                'previous_month_same_period' => $salesPrev,
                'month_change_percent' => $salesPrev > 0 ? round(($salesMonth - $salesPrev) / $salesPrev * 100, 1) : null,
                'invoices_month' => $invoicesMonth,
                'average_basket' => $invoicesMonth > 0 ? $this->f($salesMonth / $invoicesMonth) : 0,
            ],
            'receivables' => ['total' => $receivable, 'overdue_amount' => $overdueAmount, 'overdue_count' => $overdueCount],
            'payables' => ['total' => $payable, 'count' => $payableCount],
            'stock' => ['value' => $stockValue, 'low_count' => $lowCount, 'low' => $lowStock, 'products' => Product::where('is_active', true)->count()],
            'pending' => [
                'open_quotes' => (clone $quotes)->count(),
                'open_quotes_total' => $this->f((clone $quotes)->sum('total')),
                'awaiting_reception' => (clone $awaiting)->count(),
                'late_receptions' => (clone $awaiting)->whereNotNull('expected_date')->where('expected_date', '<', $today)->count(),
            ],
            'register_open' => (bool) $register,
            'branch_id' => $branch,
            'series' => $series,
            'top_products' => $top,
            'customers' => DB::table('customers')->where('is_walk_in', false)->where('is_active', true)->count(),
        ]);
    }
}
