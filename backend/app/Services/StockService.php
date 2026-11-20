<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBatch;
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
     * @param  array<int, array{batch_number: string, expires_at?: ?string, quantity: float|string}>|null  $batches
     *                                                                                                              lots reçus (entrées d'un produit suivi par lots)
     * @param  bool  $sale  vente : les lots périmés ne sont jamais servis
     */
    public function move(int $productId, string $type, string|float $quantity, ?string $reason = null, ?int $userId = null, ?int $branchId = null, ?array $batches = null, bool $sale = false): StockMovement
    {
        $branchId ??= app(CurrentBranch::class)->forWrite();

        return DB::transaction(function () use ($productId, $type, $quantity, $reason, $userId, $branchId, $batches, $sale) {
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

            $allocations = null;
            if ($product->track_batches) {
                if ($delta > 0) {
                    $this->addToBatches($productId, $branchId, $batches, $delta);
                } elseif ($delta < 0) {
                    $allocations = $this->takeFromBatches($productId, $branchId, -$delta, $current, $sale);
                }
            }

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
                'batches' => $allocations ?: ($delta > 0 && $batches ? $this->cleanBatches($batches) : null),
            ]);
        });
    }

    /** @return array<int, array{batch_number: string, expires_at: ?string, quantity: float}> */
    private function cleanBatches(array $batches): array
    {
        return collect($batches)->filter(fn ($b) => filled($b['batch_number'] ?? null) && (float) ($b['quantity'] ?? 0) > 0)
            ->map(fn ($b) => [
                'batch_number' => trim((string) $b['batch_number']),
                'expires_at' => filled($b['expires_at'] ?? null) ? (string) $b['expires_at'] : null,
                'quantity' => round((float) $b['quantity'], 3),
            ])->values()->all();
    }

    /** Entrée : ajoute les quantités aux lots indiqués (créés au besoin). Le reste, sans lot, n'est pas suivi. */
    private function addToBatches(int $productId, int $branchId, ?array $batches, float $total): void
    {
        $clean = $this->cleanBatches($batches ?? []);
        if (round(array_sum(array_column($clean, 'quantity')), 3) > round($total, 3)) {
            throw ValidationException::withMessages(['quantity' => ['La somme des lots dépasse la quantité reçue.']]);
        }
        foreach ($clean as $b) {
            $batch = ProductBatch::where(['product_id' => $productId, 'branch_id' => $branchId, 'batch_number' => $b['batch_number']])->lockForUpdate()->first();
            if ($batch) {
                $batch->forceFill([
                    'quantity' => round((float) $batch->quantity + $b['quantity'], 3),
                    'expires_at' => $b['expires_at'] ?? $batch->expires_at,
                ])->save();
            } else {
                ProductBatch::create(['product_id' => $productId, 'branch_id' => $branchId, 'batch_number' => $b['batch_number'], 'expires_at' => $b['expires_at'], 'quantity' => $b['quantity']]);
            }
        }
    }

    /**
     * Sortie : prélève d'abord les lots qui périment le plus tôt (premier périmé, premier sorti). Une vente ne sert jamais
     * un lot périmé. Ce qui n'est pas rattaché à un lot (stock d'avant le suivi par lots) est prélevé en dernier.
     *
     * @return array<int, array{batch_number: string, expires_at: ?string, quantity: float}>
     */
    private function takeFromBatches(int $productId, int $branchId, float $needed, float $levelQuantity, bool $sale): array
    {
        $batches = ProductBatch::where(['product_id' => $productId, 'branch_id' => $branchId])->where('quantity', '>', 0)
            ->orderByRaw('expires_at asc nulls last')->orderBy('id')->lockForUpdate()->get();
        $untracked = max(0, round($levelQuantity - (float) $batches->sum('quantity'), 3));
        $today = now()->toDateString();
        $taken = [];
        $left = $needed;

        foreach ($batches as $batch) {
            if ($left <= 0) {
                break;
            }
            if ($sale && $batch->expires_at && $batch->expires_at->toDateString() < $today) {
                continue;
            }
            $qty = min($left, (float) $batch->quantity);
            $batch->forceFill(['quantity' => round((float) $batch->quantity - $qty, 3)])->save();
            $taken[] = ['batch_number' => $batch->batch_number, 'expires_at' => $batch->expires_at?->toDateString(), 'quantity' => round($qty, 3)];
            $left = round($left - $qty, 3);
        }

        if ($left > $untracked + 0.0005) {
            $expired = $sale ? (float) $batches->filter(fn ($b) => $b->expires_at && $b->expires_at->toDateString() < $today)->sum('quantity') : 0;
            throw ValidationException::withMessages(['quantity' => [
                $expired > 0 ? 'Stock insuffisant : '.rtrim(rtrim(number_format($expired, 3, ',', ''), '0'), ',').' en lots périmés, non vendables.' : 'Stock insuffisant.',
            ]]);
        }

        return $taken;
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
