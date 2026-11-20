<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SalesDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Règles métier des devis et factures. Les totaux sont toujours recalculés ici
 * à partir des lignes : on ne fait jamais confiance aux montants envoyés par le client.
 */
class DocumentService
{
    public function __construct(private StockService $stock, private NumberSequence $numbers) {}

    /** @return array{0: array, 1: float, 2: float} lignes normalisées, total HT, total TVA */
    public function normalizeLines(array $lines): array
    {
        $out = [];
        $subtotal = 0.0;
        $tax = 0.0;
        // Produits vendus au poids : montant arrondi à l'unité (pas de centimes) et poids minimum respecté.
        $weighed = \App\Models\Product::whereIn('id', collect($lines)->pluck('product_id')->filter())->where('sold_by_weight', true)->get()->keyBy('id');

        foreach (array_values($lines) as $i => $l) {
            $qty = round((float) $l['quantity'], 3);
            $price = round((float) $l['unit_price'], 2);
            $discount = round((float) ($l['discount_rate'] ?? 0), 2);
            $rate = round((float) ($l['tax_rate'] ?? 0), 2);

            $net = round($qty * $price * (1 - $discount / 100), 2);
            if ($p = $weighed->get($l['product_id'] ?? 0)) {
                if ($qty < (float) $p->min_sale_quantity) {
                    abort(422, "« {$p->name} » : poids minimum {$p->min_sale_quantity} {$p->unit}.");
                }
                $net = (float) round($net);
            }
            $subtotal += $net;
            $tax += round($net * $rate / 100, 2);

            $out[] = [
                'product_id' => $l['product_id'] ?? null,
                'position' => $i,
                'description' => $l['description'],
                'unit' => $l['unit'] ?? null,
                'quantity' => $qty,
                'unit_price' => $price,
                'discount_rate' => $discount,
                'tax_rate' => $rate,
                'line_total' => $net,
            ];
        }

        return [$out, round($subtotal, 2), round($tax, 2)];
    }

    /** Crée un brouillon (facture) ou un devis numéroté, ou met à jour un document modifiable. */
    public function save(?SalesDocument $doc, array $data, ?int $userId): SalesDocument
    {
        return DB::transaction(function () use ($doc, $data, $userId) {
            if ($doc) {
                $doc = SalesDocument::whereKey($doc->id)->lockForUpdate()->firstOrFail();
                $this->assertEditable($doc);
            }

            [$lines, $subtotal, $tax] = $this->normalizeLines($data['lines']);
            $attrs = [
                'customer_id' => $data['customer_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'tax_total' => $tax,
                'total' => round($subtotal + $tax, 2),
            ];

            if ($doc) {
                $doc->update($attrs);
                $doc->lines()->delete();
            } else {
                $doc = SalesDocument::create($attrs + [
                    'type' => $data['type'],
                    'channel' => $data['channel'] ?? 'back_office',
                    'register_session_id' => $data['register_session_id'] ?? null,
                    'branch_id' => $data['branch_id'] ?? null,
                    'status' => 'draft',
                    'user_id' => $userId,
                ]);
                // Les devis sont numérotés dès la création ; les factures à l'émission.
                if ($doc->type === 'quote') {
                    $doc->update(['number' => $this->nextNumber('quote')]);
                }
            }

            $doc->lines()->createMany($lines);

            return $this->load($doc);
        });
    }

    public function load(SalesDocument $doc): SalesDocument
    {
        return $doc->fresh([
            'customer:id,name,email,phone,address,city,tax_id,is_walk_in', 'lines', 'payments',
            'sourceQuote:id,number', 'invoice:id,number,status,source_quote_id',
        ]);
    }

    private function assertEditable(SalesDocument $doc): void
    {
        $editable = $doc->type === 'quote'
            ? in_array($doc->status, ['draft', 'sent'], true)
            : $doc->status === 'draft';

        if (! $editable) {
            abort(422, 'Ce document n\'est plus modifiable.');
        }
    }

    private function nextNumber(string $type): string
    {
        return $this->numbers->next($type);
    }

    /** Actions : send, accept, reject, reopen (devis) ; issue, cancel (facture). */
    public function transition(SalesDocument $doc, string $action, ?int $userId): SalesDocument
    {
        return DB::transaction(function () use ($doc, $action, $userId) {
            $doc = SalesDocument::whereKey($doc->id)->lockForUpdate()->firstOrFail();

            if ($doc->type === 'quote') {
                $to = match ($action) {
                    'send' => $this->expect($doc, ['draft'], 'sent'),
                    'accept' => $this->expect($doc, ['draft', 'sent'], 'accepted'),
                    'reject' => $this->expect($doc, ['draft', 'sent'], 'rejected'),
                    'reopen' => $this->expect($doc, ['accepted', 'rejected'], 'draft'),
                    default => abort(422, 'Action impossible sur un devis.'),
                };
                $doc->update(['status' => $to]);
            } else {
                match ($action) {
                    'issue' => $this->issue($doc, $userId),
                    'cancel' => $this->cancel($doc, $userId),
                    default => abort(422, 'Action impossible sur une facture.'),
                };
            }

            return $this->load($doc);
        });
    }

    private function expect(SalesDocument $doc, array $from, string $to): string
    {
        if (! in_array($doc->status, $from, true)) {
            abort(422, 'Cette action n\'est pas possible dans l\'état actuel du document.');
        }

        return $to;
    }

    /** Émission : attribue le numéro et décrémente le stock, le tout dans une même transaction. */
    private function issue(SalesDocument $doc, ?int $userId): void
    {
        $this->expect($doc, ['draft'], 'issued');
        app(PlanLimits::class)->ensureCanAdd('invoices_month');
        $lines = $doc->lines()->get();
        if ($lines->isEmpty()) {
            abort(422, 'Ajoutez au moins une ligne avant d\'émettre la facture.');
        }

        $number = $this->nextNumber('invoice');

        foreach ($lines->whereNotNull('product_id') as $line) {
            try {
                $this->stock->move($line->product_id, 'out', (float) $line->quantity, "Facture $number", $userId, $doc->branch_id, sale: true);
            } catch (ValidationException $e) {
                abort(422, "« {$line->description} » : ".($e->errors()['quantity'][0] ?? 'stock insuffisant.'));
            }
        }

        $doc->update(['number' => $number, 'status' => 'issued']);
    }

    /** Annulation : remet en stock les quantités sorties. Impossible si des paiements existent. */
    private function cancel(SalesDocument $doc, ?int $userId): void
    {
        $this->expect($doc, ['issued', 'partial'], 'cancelled');
        if ((float) $doc->amount_paid > 0) {
            abort(422, 'Supprimez d\'abord les paiements enregistrés avant d\'annuler la facture.');
        }

        foreach ($doc->lines()->whereNotNull('product_id')->get() as $line) {
            $this->stock->move($line->product_id, 'in', (float) $line->quantity, "Annulation facture {$doc->number}", $userId, $doc->branch_id);
        }

        $doc->update(['status' => 'cancelled']);
    }

    /** Transforme un devis accepté en facture brouillon (copie des lignes). */
    public function convert(SalesDocument $quote, ?int $userId): SalesDocument
    {
        return DB::transaction(function () use ($quote, $userId) {
            $quote = SalesDocument::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($quote->type !== 'quote' || $quote->status !== 'accepted') {
                abort(422, 'Seul un devis accepté peut être transformé en facture.');
            }

            $invoice = SalesDocument::create([
                'branch_id' => $quote->branch_id,
                'type' => 'invoice',
                'status' => 'draft',
                'customer_id' => $quote->customer_id,
                'issue_date' => now()->toDateString(),
                'due_date' => null,
                'notes' => $quote->notes,
                'subtotal' => $quote->subtotal,
                'tax_total' => $quote->tax_total,
                'total' => $quote->total,
                'source_quote_id' => $quote->id,
                'user_id' => $userId,
            ]);

            $invoice->lines()->createMany(
                $quote->lines()->get()->map(fn ($l) => collect($l->only([
                    'product_id', 'position', 'description', 'unit', 'quantity',
                    'unit_price', 'discount_rate', 'tax_rate', 'line_total',
                ]))->all())->all(),
            );

            $quote->update(['status' => 'invoiced']);

            return $this->load($invoice);
        });
    }

    public function delete(SalesDocument $doc): void
    {
        DB::transaction(function () use ($doc) {
            $doc = SalesDocument::whereKey($doc->id)->lockForUpdate()->firstOrFail();

            if ($doc->type === 'invoice' && $doc->status !== 'draft') {
                abort(422, 'Une facture émise ne peut pas être supprimée : annulez-la.');
            }
            if ($doc->type === 'quote' && $doc->status === 'invoiced') {
                abort(422, 'Ce devis a été transformé en facture.');
            }

            // Un brouillon issu d'un devis : le devis redevient « accepté » et peut être refacturé.
            if ($doc->source_quote_id) {
                SalesDocument::whereKey($doc->source_quote_id)->where('status', 'invoiced')->update(['status' => 'accepted']);
            }

            $doc->delete();
        });
    }

    public function addPayment(SalesDocument $doc, array $data, ?int $userId): SalesDocument
    {
        return DB::transaction(function () use ($doc, $data, $userId) {
            $doc = SalesDocument::whereKey($doc->id)->lockForUpdate()->firstOrFail();
            if ($doc->type !== 'invoice' || ! in_array($doc->status, ['issued', 'partial'], true)) {
                abort(422, 'Les paiements ne s\'enregistrent que sur une facture émise non soldée.');
            }

            $amount = round((float) $data['amount'], 2);
            $balance = round((float) $doc->total - (float) $doc->amount_paid, 2);
            if ($amount > $balance) {
                abort(422, 'Le montant dépasse le solde restant ('.number_format($balance, 2, ',', ' ').').');
            }

            Payment::create([
                'branch_id' => $doc->branch_id,
                'sales_document_id' => $doc->id,
                'register_session_id' => $data['register_session_id'] ?? null,
                'amount' => $amount,
                'method' => $data['method'],
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'reference' => $data['reference'] ?? null,
                'user_id' => $userId,
            ]);

            $this->refreshPaymentStatus($doc);

            return $this->load($doc);
        });
    }

    public function removePayment(SalesDocument $doc, int $paymentId): SalesDocument
    {
        return DB::transaction(function () use ($doc, $paymentId) {
            $doc = SalesDocument::whereKey($doc->id)->lockForUpdate()->firstOrFail();
            if ($doc->type !== 'invoice' || ! in_array($doc->status, ['issued', 'partial', 'paid'], true)) {
                abort(422, 'Impossible de modifier les paiements de ce document.');
            }

            $doc->payments()->whereKey($paymentId)->firstOrFail()->delete();
            $this->refreshPaymentStatus($doc);

            return $this->load($doc);
        });
    }

    private function refreshPaymentStatus(SalesDocument $doc): void
    {
        $paid = round((float) $doc->payments()->sum('amount'), 2);
        $status = $paid >= round((float) $doc->total, 2) && $paid > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'issued');
        $doc->update(['amount_paid' => $paid, 'status' => $status]);
    }
}
