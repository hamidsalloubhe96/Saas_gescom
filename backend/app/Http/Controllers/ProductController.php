<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\StockService;
use App\Support\Trigram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    private const NUMERIC_DEFAULTS = ['purchase_price', 'sale_price', 'tax_rate', 'min_stock', 'min_sale_quantity'];

    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'active_ingredient' => ['nullable', 'string', 'max:190'],
            'dosage' => ['nullable', 'string', 'max:60'],
            'form' => ['nullable', 'string', 'max:60'],
            'track_batches' => ['sometimes', 'boolean'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($ignoreId)],
            'barcode' => ['nullable', 'string', 'max:60', Rule::unique('products', 'barcode')->ignore($ignoreId), Rule::unique('product_barcodes', 'barcode')],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'unit' => ['nullable', 'string', 'max:20'],
            'plu' => ['nullable', 'regex:/^\d{1,5}$/', Rule::unique('products', 'plu')->ignore($ignoreId)],
            'units' => ['sometimes', 'array', 'max:10'],
            'units.*.name' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'units.*.quantity' => ['required', 'numeric', 'gt:1', 'max:999999'],
            'units.*.sale_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'units.*.barcode' => ['nullable', 'string', 'max:60', 'distinct'],
            'tiers' => ['sometimes', 'array', 'max:10'],
            'tiers.*.min_quantity' => ['required', 'numeric', 'gt:1', 'max:999999', 'distinct'],
            'tiers.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'is_manufactured' => ['sometimes', 'boolean'],
            'batch_yield' => ['nullable', 'numeric', 'gt:0', 'max:999999'],
            'sellable' => ['sometimes', 'boolean'],
            'recipe' => ['sometimes', 'array', 'max:30'],
            'recipe.*.ingredient_id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'recipe.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'sold_by_weight' => ['sometimes', 'boolean'],
            'min_sale_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
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
        if (array_key_exists('plu', $data)) {
            // Sans zéros de tête : « 012 » et « 12 » sont le même code court.
            $data['plu'] = filled($data['plu']) ? (string) ((int) $data['plu']) : null;
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
        $query = Product::query()->forBranch($branch)->with('category:id,name', 'barcodes:id,product_id,barcode,label,quantity', 'units', 'priceTiers', 'recipeItems.ingredient:id,name,unit,purchase_price')->orderBy('name');

        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(function ($q) use ($like, $term) {
                $q->where('name', 'ilike', $like)
                    ->orWhere('active_ingredient', 'ilike', $like)
                    ->orWhere('sku', 'ilike', $like)
                    ->orWhere('barcode', 'ilike', $like)
                    ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', 'ilike', $like));
                // Tolérance aux fautes de frappe (« dolipran » trouve « Doliprane ») quand pg_trgm est disponible.
                if (Trigram::available() && mb_strlen($term) >= 4) {
                    $q->orWhereRaw('public.word_similarity(?, products.name) >= 0.5', [$term])
                        ->orWhereRaw('public.word_similarity(?, products.active_ingredient) >= 0.5', [$term]);
                }
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        if ($request->filled('sellable')) {
            // La caisse ne propose pas les ingrédients (farine, levure…).
            $query->where('sellable', $request->boolean('sellable'));
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
            'batch_number' => ['nullable', 'string', 'max:60'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $initial = (float) ($data['initial_stock'] ?? 0);
        $batch = $this->batchFrom($data, $initial);
        [$units, $tiers, $recipe] = [$data['units'] ?? null, $data['tiers'] ?? null, $data['recipe'] ?? null];
        unset($data['initial_stock'], $data['batch_number'], $data['expires_at'], $data['units'], $data['tiers'], $data['recipe']);

        $product = DB::transaction(function () use ($data, $initial, $batch, $stock, $request, $units, $tiers, $recipe) {
            $product = Product::create($this->withDefaults($data));
            $this->syncPricing($product, $units, $tiers);
            $this->syncRecipe($product, $recipe);

            // SKU automatique si non fourni : PRD-00042
            if (blank($product->sku)) {
                $product->update(['sku' => sprintf('PRD-%05d', $product->id)]);
            }
            if ($initial > 0) {
                $stock->move($product->id, 'in', $initial, 'Stock initial', $request->user()->id, batches: $product->track_batches ? $batch : null);
            }

            return $product;
        });

        return response()->json($product->refresh()->load('category:id,name', 'units', 'priceTiers', 'recipeItems.ingredient:id,name,unit,purchase_price'), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Product::forBranch(app(\App\Services\CurrentBranch::class)->id())->with('category:id,name', 'barcodes', 'units', 'priceTiers', 'recipeItems.ingredient:id,name,unit,purchase_price')->findOrFail($id)->applyBranch());
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $data = $request->validate($this->rules($id));
        [$units, $tiers, $recipe] = [$data['units'] ?? null, $data['tiers'] ?? null, $data['recipe'] ?? null];
        unset($data['units'], $data['tiers'], $data['recipe']);

        DB::transaction(function () use ($product, $data, $units, $tiers, $recipe) {
            $product->update($this->withDefaults($data));
            $this->syncPricing($product, $units, $tiers);
            $this->syncRecipe($product, $recipe);
        });

        return response()->json($product->refresh()->load('category:id,name', 'units', 'priceTiers', 'recipeItems.ingredient:id,name,unit,purchase_price'));
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
            'batch_number' => ['nullable', 'string', 'max:60'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $batch = null;
        if ($data['type'] === 'in' && $product->track_batches) {
            if (blank($data['batch_number'] ?? null) || blank($data['expires_at'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['batch_number' => ['Indiquez le numéro de lot et la date de péremption.']]);
            }
            $batch = $this->batchFrom($data, (float) $data['quantity']);
        }
        $movement = $stock->move($id, $data['type'], $data['quantity'], $data['reason'] ?? null, $request->user()->id, batches: $batch);

        return response()->json([
            'movement' => $movement->load('user:id,name'),
            'product' => Product::forBranch(app(\App\Services\CurrentBranch::class)->id())->with('category:id,name')->find($id)->applyBranch(),
        ], 201);
    }

    /** Remplace la recette (null = inchangée). Un ingrédient ne peut être ni le produit lui-même ni un autre produit fabriqué. */
    private function syncRecipe(Product $product, ?array $recipe): void
    {
        if ($recipe === null) {
            return;
        }
        $ids = collect($recipe)->pluck('ingredient_id');
        if ($ids->contains($product->id)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['recipe' => ['Un produit ne peut pas être son propre ingrédient.']]);
        }
        if (Product::whereIn('id', $ids)->where('is_manufactured', true)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['recipe' => ['Un ingrédient ne peut pas être un produit fabriqué.']]);
        }
        $product->recipeItems()->delete();
        if ($product->is_manufactured) {
            foreach ($recipe as $r) {
                $product->recipeItems()->create(['ingredient_id' => $r['ingredient_id'], 'quantity' => $r['quantity']]);
            }
        }
    }

    /** Remplace les conditionnements et les paliers du produit (null = inchangés). Un code-barres ne peut servir qu'une fois. */
    private function syncPricing(Product $product, ?array $units, ?array $tiers): void
    {
        if ($units !== null) {
            foreach ($units as $u) {
                $code = $u['barcode'] ?? null;
                if (filled($code) && (
                    Product::where('barcode', $code)->exists()
                    || \App\Models\ProductBarcode::where('barcode', $code)->exists()
                    || \App\Models\ProductUnit::where('barcode', $code)->where('product_id', '!=', $product->id)->exists()
                )) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['units' => ["Le code-barres {$code} est déjà utilisé."]]);
                }
            }
            $product->units()->delete();
            foreach ($units as $u) {
                $product->units()->create(['name' => trim($u['name']), 'quantity' => $u['quantity'], 'sale_price' => $u['sale_price'], 'barcode' => filled($u['barcode'] ?? null) ? $u['barcode'] : null]);
            }
        }
        if ($tiers !== null) {
            $product->priceTiers()->delete();
            foreach ($tiers as $t) {
                $product->priceTiers()->create(['min_quantity' => $t['min_quantity'], 'unit_price' => $t['unit_price']]);
            }
        }
    }

    private function batchFrom(array $data, float $quantity): ?array
    {
        return filled($data['batch_number'] ?? null) && $quantity > 0
            ? [['batch_number' => $data['batch_number'], 'expires_at' => $data['expires_at'] ?? null, 'quantity' => $quantity]]
            : null;
    }

    /** Lots d'un produit dans le point de vente courant (ou tous), le plus proche de la péremption d'abord. */
    public function batches(int $id): JsonResponse
    {
        $branch = app(\App\Services\CurrentBranch::class)->id();
        $rows = \App\Models\ProductBatch::where('product_id', $id)->where('quantity', '>', 0)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch))
            ->with('branch:id,name')->orderByRaw('expires_at asc nulls last')->get();

        return response()->json($rows);
    }

    /** Équivalents : mêmes principes actifs (génériques et autres marques), disponibles d'abord. */
    public function equivalents(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        if (blank($product->active_ingredient)) {
            return response()->json([]);
        }
        $branch = app(\App\Services\CurrentBranch::class)->id();
        $rows = Product::query()->forBranch($branch)
            ->whereRaw('lower(active_ingredient) = ?', [mb_strtolower(trim($product->active_ingredient))])
            ->where('products.id', '!=', $id)->where('is_active', true)->orderBy('name')->limit(30)->get()
            ->each->applyBranch()->sortByDesc(fn ($p) => (float) $p->stock_quantity > 0)->values();

        return response()->json($rows->map->only(['id', 'name', 'dosage', 'form', 'sale_price', 'stock_quantity']));
    }
}
