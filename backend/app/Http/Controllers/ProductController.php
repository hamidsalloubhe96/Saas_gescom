<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    private const NUMERIC_DEFAULTS = ['purchase_price', 'sale_price', 'tax_rate', 'min_stock'];

    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($ignoreId)],
            'barcode' => ['nullable', 'string', 'max:60', Rule::unique('products', 'barcode')->ignore($ignoreId)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'unit' => ['nullable', 'string', 'max:20'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** Les champs numériques vides prennent leur valeur par défaut (les colonnes sont NOT NULL). */
    private function withDefaults(array $data): array
    {
        foreach (self::NUMERIC_DEFAULTS as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = 0;
            }
        }
        if (array_key_exists('unit', $data) && $data['unit'] === null) {
            $data['unit'] = 'pièce';
        }

        return $data;
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'active' => ['nullable', 'in:0,1'],
            'low_stock' => ['nullable', 'in:0,1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $branch = app(\App\Services\CurrentBranch::class)->id();
        $query = Product::query()->forBranch($branch)->with('category:id,name')->orderBy('name');

        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('sku', 'ilike', $like)
                ->orWhere('barcode', 'ilike', $like));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        if ($request->boolean('low_stock')) {
            // Stock bas = seuil défini (> 0) et quantité inférieure ou égale au seuil.
            $query->where('min_stock', '>', 0);
            $branch
                ? $query->whereRaw('coalesce((select quantity from stock_levels where stock_levels.product_id = products.id and stock_levels.branch_id = ?), 0) <= products.min_stock', [$branch])
                : $query->whereColumn('stock_quantity', '<=', 'min_stock');
        }

        $page = $query->paginate($request->integer('per_page', 15));
        $page->getCollection()->each->applyBranch();

        return response()->json($page);
    }

    public function store(Request $request, StockService $stock): JsonResponse
    {
        app(\App\Services\PlanLimits::class)->ensureCanAdd('products');

        $data = $request->validate($this->rules() + [
            'initial_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);
        $initial = (float) ($data['initial_stock'] ?? 0);
        unset($data['initial_stock']);

        $product = DB::transaction(function () use ($data, $initial, $stock, $request) {
            $product = Product::create($this->withDefaults($data));

            // SKU automatique si non fourni : PRD-00042
            if (blank($product->sku)) {
                $product->update(['sku' => sprintf('PRD-%05d', $product->id)]);
            }
            if ($initial > 0) {
                $stock->move($product->id, 'in', $initial, 'Stock initial', $request->user()->id);
            }

            return $product;
        });

        return response()->json($product->refresh()->load('category:id,name'), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Product::forBranch(app(\App\Services\CurrentBranch::class)->id())->with('category:id,name')->findOrFail($id)->applyBranch());
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($this->withDefaults($request->validate($this->rules($id))));

        return response()->json($product->refresh()->load('category:id,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        Product::findOrFail($id)->delete(); // l'historique des mouvements est supprimé avec lui

        return response()->json(null, 204);
    }

    public function movements(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        return response()->json(
            $product->movements()->when(app(\App\Services\CurrentBranch::class)->id(), fn ($q, $b) => $q->where('branch_id', $b))->with('user:id,name', 'branch:id,name')->latest('id')
                ->paginate($request->integer('per_page', 10)),
        );
    }

    public function addMovement(Request $request, int $id, StockService $stock): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:in,out,adjust'],
            // in/out : quantité > 0 ; adjust : quantité comptée (>= 0)
            'quantity' => ['required', 'numeric', 'max:999999999', $request->input('type') === 'adjust' ? 'min:0' : 'gt:0'],
            'reason' => ['nullable', 'string', 'max:190'],
        ]);

        $movement = $stock->move($id, $data['type'], $data['quantity'], $data['reason'] ?? null, $request->user()->id);

        return response()->json([
            'movement' => $movement->load('user:id,name'),
            'product' => Product::forBranch(app(\App\Services\CurrentBranch::class)->id())->with('category:id,name')->find($id)->applyBranch(),
        ], 201);
    }
}
