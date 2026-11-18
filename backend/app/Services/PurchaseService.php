<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use Illuminate\Support\Facades\DB;

/**
 * Règles métier des commandes fournisseurs : totaux recalculés côté serveur,
 * réception (partielle possible) qui alimente le stock, règlements fournisseurs.
 */
class PurchaseService
{
    public function __construct(private StockService $stock, private NumberSequence $numbers) {}

    /** @return array{0: array, 1: float, 2: float} */
    public function normalizeLines(array $lines): array
    {
        $out = [];
        $subtotal = 0.0;
        $tax = 0.0;

        foreach (array_values($lines) as $i => $l) {
            $qty = round((float) $l['quantity'], 3);
            $price = round((float) $l['unit_price'], 2);
            $rate = round((float) ($l['tax_rate'] ?? 0), 2);

            $net = round($qty * $price, 2);
            $subtotal += $net;
            $tax += round($net * $rate / 100, 2);

            $out[] = [
                'product_id' => $l['product_id'] ?? null,
                'position' => $i,
                'description' => $l['description'],
                'unit' => $l['unit'] ?? null,
                'quantity' => $qty,
                'unit_price' => $price,
                'tax_rate' => $rate,
                'line_total' => $net,
            ];
        }

        return [$out, round($subtotal, 2), round($tax, 2)];
    }

    public function load(PurchaseOrder $po): PurchaseOrder
    {
        return $po->fresh(['supplier:id,name,email,phone,address,city,tax_id', 'lines', 'payments']);
    }

    public function save(?PurchaseOrder $po, array $data, ?int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data, $userId) {
            if ($po) {
                $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
                if ($po->status !== 'draft') {
                    abort(422, 'Seule une commande en brouillon peut être modifiée.');
                }
            }

            [$lines, $subtotal, $tax] = $this->normalizeLines($data['lines']);
            $attrs = [
                'supplier_id' => $data['supplier_id'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'tax_total' => $tax,
                'total' => round($subtotal + $tax, 2),
            ];

            if ($po) {
                $po->update($attrs);
                $po->lines()->delete();
            } else {
                $po = PurchaseOrder::create($attrs + [
                    'number' => $this->numbers->next('purchase'),
                    'status' => 'draft',
                    'user_id' => $userId,
                ]);
            }

            $po->lines()->createMany($lines);

            return $this->load($po);
        });
    }

    public function delete(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            if ($po->status !== 'draft') {
                abort(422, 'Seule une commande en brouillon peut être supprimée. Annulez-la plutôt.');
            }
            $po->delete();
        });
    }

    /** Actions : order (brouillon → commandée), cancel (avant toute réception), close (clôturer une commande partielle). */
    public function transition(PurchaseOrder $po, string $action): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $action) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();

            switch ($action) {
                case 'order':
                    $this->expect($po, ['draft']);
                    if (! $po->lines()->exists()) {
                        abort(422, 'Ajoutez au moins une ligne avant de passer la commande.');
                    }
                    $po->update(['status' => 'ordered']);
                    break;

                case 'cancel':
                    $this->expect($po, ['ordered']);
                    if ((float) $po->amount_paid > 0) {
                        abort(422, 'Supprimez d\'abord les règlements enregistrés avant d\'annuler la commande.');
                    }
                    $po->update(['status' => 'cancelled']);
                    break;

                case 'close':
                    // Le reliquat non livré ne sera plus attendu.
                    $this->expect($po, ['partial']);
                    $po->update(['status' => 'received']);
                    break;
            }

            return $this->load($po);
        });
    }

    private function expect(PurchaseOrder $po, array $from): void
    {
        if (! in_array($po->status, $from, true)) {
            abort(422, 'Cette action n\'est pas possible dans l\'état actuel de la commande.');
        }
    }

    /**
     * Réception de marchandises (totale ou partielle). Chaque ligne reçue crée une entrée de stock.
     *
     * @param  array<int, array{line_id: int, quantity: float|string}>  $items
     */
    public function receive(PurchaseOrder $po, array $items, ?int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $items, $userId) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            $this->expect($po, ['ordered', 'partial']);

            $lines = $po->lines()->lockForUpdate()->get()->keyBy('id');
            $received = 0;

            foreach ($items as $item) {
                $qty = round((float) $item['quantity'], 3);
                if ($qty <= 0) {
                    continue;
                }

                $line = $lines->get((int) $item['line_id']) ?? abort(422, 'Ligne inconnue pour cette commande.');
                $remaining = round((float) $line->quantity - (float) $line->quantity_received, 3);
                if ($qty > $remaining) {
                    abort(422, "« {$line->description} » : quantité reçue supérieure au reste à recevoir ({$remaining}).");
                }

                if ($line->product_id) {
                    $this->stock->move($line->product_id, 'in', $qty, "Réception {$po->number}", $userId);
                }
                $line->update(['quantity_received' => round((float) $line->quantity_received + $qty, 3)]);
                $received++;
            }

            if ($received === 0) {
                abort(422, 'Saisissez au moins une quantité reçue.');
            }

            $complete = $lines->every(fn ($l) => round((float) $l->quantity - (float) $l->fresh()->quantity_received, 3) <= 0);
            $po->update(['status' => $complete ? 'received' : 'partial']);

            return $this->load($po);
        });
    }

    public function addPayment(PurchaseOrder $po, array $data, ?int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data, $userId) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            $this->expect($po, ['ordered', 'partial', 'received']);

            $amount = round((float) $data['amount'], 2);
            $balance = round((float) $po->total - (float) $po->amount_paid, 2);
            if ($amount > $balance) {
                abort(422, 'Le montant dépasse le solde restant ('.number_format($balance, 2, ',', ' ').').');
            }

            PurchasePayment::create([
                'purchase_order_id' => $po->id,
                'amount' => $amount,
                'method' => $data['method'],
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'reference' => $data['reference'] ?? null,
                'user_id' => $userId,
            ]);

            $po->update(['amount_paid' => round((float) $po->payments()->sum('amount'), 2)]);

            return $this->load($po);
        });
    }

    public function removePayment(PurchaseOrder $po, int $paymentId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $paymentId) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            $this->expect($po, ['ordered', 'partial', 'received']);

            $po->payments()->whereKey($paymentId)->firstOrFail()->delete();
            $po->update(['amount_paid' => round((float) $po->payments()->sum('amount'), 2)]);

            return $this->load($po);
        });
    }
}
