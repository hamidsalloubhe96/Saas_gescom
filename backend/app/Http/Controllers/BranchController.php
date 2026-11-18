<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchPrice;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\CurrentBranch;
use App\Services\PlanLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Points de vente : liste, création, modification, vue comparative et prix locaux (siège uniquement). */
class BranchController extends Controller
{
    private function rules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('branches', 'code')->ignore($id)],
            'region' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    /** Charge utile envoyée avec /login et /me : point de vente de l'utilisateur ou liste pour le siège. */
    public static function payloadFor(User $user): array
    {
        $own = $user->branch_id ? Branch::find($user->branch_id, ['id', 'name', 'code', 'is_main']) : null;

        return [
            'branch' => $own,
            'branches' => $own ? [$own] : Branch::where('is_active', true)->orderByDesc('is_main')->orderBy('name')->get(['id', 'name', 'code', 'is_main', 'region']),
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(Branch::orderByDesc('is_main')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        app(PlanLimits::class)->ensureCanAdd('branches');
        $data = $request->validate($this->rules());
        $data['code'] = strtoupper($data['code'] ?? '') ?: $this->makeCode($data['name']);

        return response()->json(Branch::create($data)->refresh(), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $data = $request->validate($this->rules($id) + ['is_active' => ['sometimes', 'boolean']]);
        if (! empty($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        } else {
            unset($data['code']);
        }

        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $branch->is_active) {
            abort_if($branch->is_main, 422, 'Le siège ne peut pas être désactivé.');
            abort_if(StockLevel::where('branch_id', $id)->where('quantity', '>', 0)->exists(), 422, 'Ce point de vente a encore du stock : transférez-le ou régularisez-le avant de la désactiver.');
            abort_if(DB::table('register_sessions')->where('branch_id', $id)->whereNull('closed_at')->exists(), 422, 'Une caisse est encore ouverte dans ce point de vente.');
            abort_if(DB::table('stock_transfers')->whereIn('status', ['requested', 'shipped'])->where(fn ($q) => $q->where('to_branch_id', $id)->orWhere('from_branch_id', $id))->exists(), 422, 'Des transferts sont en cours pour ce point de vente.');
        }
        if (array_key_exists('is_active', $data) && $data['is_active'] && ! $branch->is_active) {
            app(PlanLimits::class)->ensureCanAdd('branches');
        }

        $branch->update($data);

        return response()->json($branch->refresh());
    }

    /** Vue comparative : chiffres de chaque point de vente (ventes, stock, caisse). */
    public function overview(): JsonResponse
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $sales = fn (string $from) => DB::table('sales_documents')->where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'paid'])
            ->where('issue_date', '>=', $from)->selectRaw('branch_id, sum(total) as total, count(*) as n')->groupBy('branch_id')->get()->keyBy('branch_id');
        $day = $sales($today);
        $month = $sales($monthStart);

        $stock = DB::table('stock_levels as s')->join('products as p', 'p.id', '=', 's.product_id')
            ->selectRaw('s.branch_id, sum(s.quantity * p.purchase_price) as value, sum(case when p.min_stock > 0 and s.quantity <= p.min_stock and p.is_active then 1 else 0 end) as low')
            ->groupBy('s.branch_id')->get()->keyBy('branch_id');
        $open = DB::table('register_sessions')->whereNull('closed_at')->pluck('branch_id')->flip();
        $pending = DB::table('stock_transfers')->whereIn('status', ['requested', 'shipped'])->selectRaw('to_branch_id, count(*) as n')->groupBy('to_branch_id')->pluck('n', 'to_branch_id');

        $rows = Branch::orderByDesc('is_main')->orderBy('name')->get()->map(fn (Branch $b) => [
            'id' => $b->id, 'name' => $b->name, 'code' => $b->code, 'region' => $b->region, 'city' => $b->city, 'is_main' => $b->is_main, 'is_active' => $b->is_active,
            'sales_today' => round((float) ($day[$b->id]->total ?? 0), 2),
            'sales_month' => round((float) ($month[$b->id]->total ?? 0), 2),
            'invoices_month' => (int) ($month[$b->id]->n ?? 0),
            'stock_value' => round((float) ($stock[$b->id]->value ?? 0), 2),
            'low_stock' => (int) ($stock[$b->id]->low ?? 0),
            'register_open' => $open->has($b->id),
            'pending_transfers' => (int) ($pending[$b->id] ?? 0),
        ]);

        return response()->json([
            'branches' => $rows,
            'totals' => [
                'sales_today' => round($rows->sum('sales_today'), 2),
                'sales_month' => round($rows->sum('sales_month'), 2),
                'stock_value' => round($rows->sum('stock_value'), 2),
                'low_stock' => $rows->sum('low_stock'),
            ],
        ]);
    }

    /** Stock et prix d'un produit dans chaque point de vente (siège). */
    public function productBranches(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $levels = StockLevel::where('product_id', $id)->pluck('quantity', 'branch_id');
        $prices = BranchPrice::where('product_id', $id)->pluck('sale_price', 'branch_id');

        return response()->json([
            'base_price' => $product->sale_price,
            'branches' => Branch::where('is_active', true)->orderByDesc('is_main')->orderBy('name')->get(['id', 'name', 'is_main'])->map(fn ($b) => [
                'id' => $b->id, 'name' => $b->name, 'is_main' => $b->is_main,
                'stock' => (float) ($levels[$b->id] ?? 0), 'local_price' => isset($prices[$b->id]) ? (float) $prices[$b->id] : null,
            ]),
        ]);
    }

    /** Fixe (ou retire, si null) le prix local d'un produit dans un point de vente. */
    public function setPrice(Request $request, int $id): JsonResponse
    {
        Product::findOrFail($id);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        if ($data['sale_price'] === null) {
            BranchPrice::where(['product_id' => $id, 'branch_id' => $data['branch_id']])->delete();
        } else {
            BranchPrice::updateOrCreate(['product_id' => $id, 'branch_id' => $data['branch_id']], ['sale_price' => $data['sale_price']]);
        }

        return $this->productBranches($id);
    }

    private function makeCode(string $name): string
    {
        $base = strtoupper(Str::limit(Str::slug($name, ''), 8, '')) ?: 'SUCC';
        $code = $base;
        for ($i = 2; Branch::where('code', $code)->exists(); $i++) {
            $code = $base.$i;
        }

        return $code;
    }
}
