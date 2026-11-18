<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seul point d'entrée pour modifier le stock : chaque changement crée un mouvement, met à jour la quantité
 * DU POINT DE VENTE (stock_levels) et le total du produit (products.stock_quantity), dans une transaction,
 * avec verrou sur le produit pour éviter les écarts en cas d'opérations simultanées.
 */
class StockService
{
    /**
     * @param  string  $type  in (entrée), out (sortie), adjust (inventaire : $quantity = quantité comptée),
     *                        transfer_in / transfer_out (transferts entre points de vente)
     * @param  int|null  $branchId  point de vente concerné ; par défaut le point de vente courant, sinon le siège
     */
    public function move(int $productId, string $type, string|float $quantity, ?string $reason = null, ?int $userId = null, ?int $branchId = null): StockMovement
    {
        $branchId ??= app(CurrentBranch::class)->forWrite();

        return DB::transaction(function () use ($productId, $type, $quantity, $reason, $userId, $branchId) {
            $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();

            StockLevel::insertOrIgnore(['product_id' => $productId, 'branch_id' => $branchId, 'quantity' => 0]);
            $level = StockLevel::where(['product_id' => $productId, 'branch_id' => $branchId])->lockForUpdate()->firstOrFail();

            $current = (float) $level->quantity;
            $qty = round((float) $quantity, 3);

            $delta = match ($type) {
                'in', 'transfer_in' => $qty,
                'out', 'transfer_out' => -$qty,
                'adjust' => round($qty - $current, 3),
            };

            $balance = round($current + $delta, 3);

            if ($balance < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock insuffisant (disponible : '.rtrim(rtrim(number_format($current, 3, ',', ''), '0'), ',').').'],
                ]);
            }

            $level->forceFill(['quantity' => $balance])->save();
            $product->forceFill(['stock_quantity' => round((float) $product->stock_quantity + $delta, 3)])->save();

            return $product->movements()->create([
                'branch_id' => $branchId,
                'type' => $type,
                'quantity' => $delta,
                'balance_after' => $balance,
                'reason' => $reason,
                'user_id' => $userId,
            ]);
        });
    }

    public function levelFor(int $productId, int $branchId): float
    {
        return (float) (StockLevel::where(['product_id' => $productId, 'branch_id' => $branchId])->value('quantity') ?? 0);
    }

    public function mainBranchId(): int
    {
        return (int) Branch::where('is_main', true)->value('id');
    }
}
