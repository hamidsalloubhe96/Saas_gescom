<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Services\Barcode;
use App\Services\CurrentBranch;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Codes-barres : recherche exacte (scan), génération de codes internes et inventaire par comptage. */
class BarcodeController extends Controller
{
    /** Produit dont le code-barres (ou la référence) correspond EXACTEMENT au code scanné. Stock et prix de la succursale courante. */
    public function lookup(string $code): JsonResponse
    {
        $code = trim($code);
        $base = fn () => Product::forBranch(app(CurrentBranch::class)->id())->with('category:id,name');

        // Code principal ou référence : un scan = 1 unité.
        $product = $base()->where(fn ($q) => $q->where('barcode', $code)->orWhereRaw('lower(sku) = ?', [mb_strtolower($code)]))->first();
        $quantity = 1.0;
        $label = null;

        // Code supplémentaire (carton, autre fournisseur) : un scan = la quantité déclarée pour ce code.
        if (! $product && ($extra = ProductBarcode::where('barcode', $code)->first())) {
            $product = $base()->find($extra->product_id);
            $quantity = (float) $extra->quantity;
            $label = $extra->label;
        }

        // Code d'un conditionnement (carton…) : on ajoute UN conditionnement, à son prix.
        $unit = null;
        if (! $product && ($u = \App\Models\ProductUnit::where('barcode', $code)->first())) {
            $product = $base()->find($u->product_id);
            $unit = $u->only(['id', 'name', 'quantity', 'sale_price']);
        }

        // Étiquette de balance (poids ou prix dans le code) : le produit est retrouvé par son code court (PLU).
        if (! $product && ($scale = \App\Services\ScaleBarcode::parse($code))) {
            $product = $base()->where('plu', ltrim($scale['plu'], '0') === '' ? '0' : ltrim($scale['plu'], '0'))->first();
            if ($product) {
                $price = (float) ($product->branch_price ?? $product->sale_price);
                $quantity = \App\Services\ScaleBarcode::weightKg($scale, $price);
                $label = 'Balance';
                if ($quantity <= 0) {
                    return response()->json(['message' => 'Étiquette de balance illisible : poids nul.', 'code' => 'scale_invalid'], 422);
                }
            }
        }

        if (! $product) {
            return response()->json(['message' => 'Code inconnu.', 'code' => 'barcode_unknown'], 404);
        }

        $product->load('units', 'priceTiers');

        return response()->json($product->applyBranch()->setAttribute('scan_quantity', $quantity)->setAttribute('scan_label', $label)->setAttribute('scan_unit', $unit));
    }

    /** Génère un code interne libre (EAN-13, préfixe 200). */
    public function generate(Barcode $barcodes): JsonResponse
    {
        return response()->json(['barcode' => $barcodes->nextInternal()]);
    }

    /** Codes supplémentaires d'un produit. */
    public function extras(int $id): JsonResponse
    {
        return response()->json(Product::findOrFail($id)->barcodes);
    }

    /** Ajoute un code supplémentaire (jamais déjà utilisé, ni comme code principal ni comme supplémentaire). */
    public function addExtra(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:60', 'unique:product_barcodes,barcode', 'unique:products,barcode'],
            'label' => ['nullable', 'string', 'max:60'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ], ['barcode.unique' => 'Ce code est déjà utilisé par un produit.']);

        $product->barcodes()->create($data);

        return response()->json($product->barcodes()->get(), 201);
    }

    public function removeExtra(int $id, int $barcodeId): JsonResponse
    {
        Product::findOrFail($id)->barcodes()->whereKey($barcodeId)->firstOrFail()->delete();

        return response()->json(Product::findOrFail($id)->barcodes()->get());
    }

    /** Attribue un code interne à un produit qui n'en a pas (pour pouvoir l'étiqueter). */
    public function assign(Barcode $barcodes, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        abort_if(filled($product->barcode), 422, 'Ce produit a déjà un code-barres.');
        $product->update(['barcode' => $barcodes->nextInternal()]);

        return response()->json($product->refresh());
    }

    /**
     * Inventaire : pour chaque produit compté, le stock de la succursale courante devient la quantité comptée
     * (mouvement « inventaire » tracé). Retourne les écarts.
     */
    public function inventory(Request $request, StockService $stock): JsonResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'lines.*.counted' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $changes = DB::transaction(function () use ($data, $stock, $request) {
            $out = [];
            foreach ($data['lines'] as $l) {
                $before = $stock->levelFor((int) $l['product_id'], app(CurrentBranch::class)->forWrite());
                $movement = $stock->move((int) $l['product_id'], 'adjust', $l['counted'], 'Inventaire (comptage)', $request->user()->id);
                $out[] = [
                    'product_id' => (int) $l['product_id'],
                    'name' => Product::whereKey($l['product_id'])->value('name'),
                    'before' => $before,
                    'after' => (float) $movement->balance_after,
                    'difference' => round((float) $movement->balance_after - $before, 3),
                ];
            }

            return $out;
        });

        return response()->json(['changes' => $changes, 'adjusted' => collect($changes)->where('difference', '!=', 0)->count()]);
    }
}
