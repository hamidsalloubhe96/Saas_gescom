<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RegisterSession;
use App\Models\SalesDocument;
use Illuminate\Support\Facades\DB;

/**
 * Caisse enregistreuse. Une vente comptoir est une facture émise et encaissée immédiatement :
 * numérotation, sortie de stock et paiements passent par DocumentService (aucune logique dupliquée).
 */
class PosService
{
    private const METHODS = ['cash' => 'Espèces', 'card' => 'Carte', 'transfer' => 'Virement', 'cheque' => 'Chèque', 'other' => 'Autre'];

    public function __construct(private DocumentService $documents) {}

    public function current(): ?RegisterSession
    {
        return RegisterSession::whereNull('closed_at')->where('branch_id', app(CurrentBranch::class)->forWrite())->latest('id')->first();
    }

    public function requireOpen(): RegisterSession
    {
        return $this->current() ?? abort(422, 'Aucune caisse ouverte. Ouvrez la caisse pour encaisser.');
    }

    public function walkIn(): Customer
    {
        return Customer::firstOrCreate(['is_walk_in' => true], ['name' => 'Client comptoir']);
    }

    public function open(float $openingAmount, ?int $userId): RegisterSession
    {
        return DB::transaction(function () use ($openingAmount, $userId) {
            if ($this->current()) {
                abort(422, 'Une caisse est déjà ouverte.');
            }

            return RegisterSession::create([
                'opened_by' => $userId,
                'opening_amount' => round($openingAmount, 2),
                'opened_at' => now(),
            ]);
        });
    }

    /** Chiffres de la session : ventes non annulées, encaissements par mode, espèces attendues en caisse. */
    public function summary(RegisterSession $session): array
    {
        $sales = SalesDocument::where('register_session_id', $session->id);
        $valid = (clone $sales)->where('status', '!=', 'cancelled');

        $byMethod = Payment::where('register_session_id', $session->id)
            ->selectRaw('method, sum(amount) as total')->groupBy('method')->pluck('total', 'method')
            ->map(fn ($v) => round((float) $v, 2));

        $cash = (float) ($byMethod['cash'] ?? 0);

        return [
            'sales_count' => (clone $valid)->count(),
            'cancelled_count' => (clone $sales)->where('status', 'cancelled')->count(),
            'total_sales' => round((float) (clone $valid)->sum('total'), 2),
            'payments' => $byMethod,
            'cash_in' => round($cash, 2),
            'expected_cash' => round((float) $session->opening_amount + $cash, 2),
        ];
    }

    public function close(RegisterSession $session, float $counted, ?string $notes, ?int $userId): RegisterSession
    {
        return DB::transaction(function () use ($session, $counted, $notes, $userId) {
            $session = RegisterSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->closed_at) {
                abort(422, 'Cette caisse est déjà clôturée.');
            }

            $sum = $this->summary($session);
            $counted = round($counted, 2);

            $session->update([
                'closed_at' => now(),
                'closed_by' => $userId,
                'expected_cash' => $sum['expected_cash'],
                'counted_cash' => $counted,
                'difference' => round($counted - $sum['expected_cash'], 2),
                'total_sales' => $sum['total_sales'],
                'notes' => $notes,
            ]);

            return $session->fresh();
        });
    }

    /**
     * Vente comptoir.
     *
     * @param  array{customer_id?: int|null, lines: array, payments?: array}  $data
     * @return array{document: SalesDocument, change_due: float, tendered: float}
     */
    public function sale(array $data, ?int $userId): array
    {
        return DB::transaction(function () use ($data, $userId) {
            $session = $this->requireOpen();
            $customer = ! empty($data['customer_id']) ? Customer::findOrFail($data['customer_id']) : $this->walkIn();

            // Prix, désignation et TVA viennent de la base : le client n'envoie que produit et quantité.
            $products = Product::with('units', 'priceTiers')->whereIn('id', collect($data['lines'])->pluck('product_id'))->get()->keyBy('id');
            // Quantité totale en unité de base par produit : elle détermine le palier de prix.
            $baseQty = collect($data['lines'])->filter(fn ($l) => empty($l['unit_id']))->groupBy('product_id')->map(fn ($g) => (float) $g->sum('quantity'));
            $localPrices = \App\Models\BranchPrice::where('branch_id', $session->branch_id)->pluck('sale_price', 'product_id');
            $lines = [];
            foreach ($data['lines'] as $l) {
                $p = $products[$l['product_id']];
                if (! $p->is_active) {
                    abort(422, "« {$p->name} » est inactif et ne peut pas être vendu.");
                }
                $packing = ! empty($l['unit_id']) ? $p->units->firstWhere('id', (int) $l['unit_id']) : null;
                if (! empty($l['unit_id']) && ! $packing) {
                    abort(422, "« {$p->name} » : conditionnement inconnu.");
                }
                // Prix : conditionnement > prix du point de vente > palier de quantité > prix normal.
                $price = $packing ? (float) $packing->sale_price : (isset($localPrices[$p->id]) ? (float) $localPrices[$p->id] : $p->tierPrice((float) $baseQty->get($p->id, 0)));
                $lines[] = [
                    'product_id' => $p->id,
                    'description' => $p->name,
                    'unit' => $p->unit,
                    'unit_id' => $packing?->id,
                    'quantity' => $l['quantity'],
                    'unit_price' => $price,
                    'discount_rate' => $l['discount_rate'] ?? 0,
                    'tax_rate' => $p->tax_rate,
                ];
            }

            $doc = $this->documents->save(null, [
                'type' => 'invoice',
                'channel' => 'pos',
                'branch_id' => $session->branch_id,
                'register_session_id' => $session->id,
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'lines' => $lines,
            ], $userId);

            // Émission : numéro définitif + sortie de stock (refus si stock insuffisant, tout est annulé).
            $doc = $this->documents->transition($doc, 'issue', $userId);

            $remaining = round((float) $doc->total, 2);
            $tendered = 0.0;
            $applied = 0.0;

            foreach ($data['payments'] ?? [] as $p) {
                $amount = round((float) $p['amount'], 2);
                $tendered += $amount;

                // Seul l'espèce peut dépasser le dû : l'excédent est rendu au client.
                if ($amount > $remaining) {
                    if ($p['method'] !== 'cash') {
                        abort(422, 'Un paiement par '.mb_strtolower(self::METHODS[$p['method']]).' ne peut pas dépasser le montant dû ('.number_format($remaining, 2, ',', ' ').').');
                    }
                    $amount = $remaining;
                }
                if ($amount <= 0) {
                    continue;
                }

                $doc = $this->documents->addPayment($doc, [
                    'amount' => $amount,
                    'method' => $p['method'],
                    'paid_at' => now()->toDateString(),
                    'branch_id' => $session->branch_id,
                    'register_session_id' => $session->id,
                ], $userId);

                $remaining = round($remaining - $amount, 2);
                $applied += $amount;
            }

            // Une vente anonyme doit être soldée ; un client identifié peut laisser un solde (vente à crédit).
            if ($customer->is_walk_in && $remaining > 0) {
                abort(422, 'Règlement incomplet : il manque '.number_format($remaining, 2, ',', ' ').'.');
            }

            return [
                'document' => $this->documents->load($doc),
                'change_due' => round($tendered - $applied, 2),
                'tendered' => round($tendered, 2),
            ];
        });
    }

    /** Annulation d'une vente de la session ouverte : paiements retirés, stock remis, facture annulée. */
    public function void(int $documentId, ?int $userId): SalesDocument
    {
        return DB::transaction(function () use ($documentId, $userId) {
            $session = $this->requireOpen();
            $doc = SalesDocument::where('channel', 'pos')->findOrFail($documentId);

            if ($doc->register_session_id !== $session->id) {
                abort(422, 'Seules les ventes de la session ouverte peuvent être annulées.');
            }
            if ($doc->status === 'cancelled') {
                abort(422, 'Cette vente est déjà annulée.');
            }

            foreach ($doc->payments()->get() as $payment) {
                $doc = $this->documents->removePayment($doc, $payment->id);
            }

            return $this->documents->transition($doc, 'cancel', $userId);
        });
    }
}
