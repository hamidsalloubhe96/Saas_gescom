<?php

namespace App\Http\Controllers;

use App\Models\Product;
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
        $product = Product::forBranch(app(CurrentBranch::class)->id())->with('category:id,name')
            ->where(fn ($q) => $q->where('barcode', $code)->orWhereRaw('lower(sku) = ?', [mb_strtolower($code)]))
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Code inconnu.', 'code' => 'barcode_unknown'], 404);
        }

        return response()->json($product->applyBranch());
    }

    /** Génère un code interne libre (EAN-13, préfixe 200). */
    public function generate(Barcode $barcodes): JsonResponse
    {
        return response()->json(['barcode' => $barcodes->nextInternal()]);
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
