<?php

namespace App\Services;

use App\Models\DailyUnsold;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionLine;
use App\Models\StockLevel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Boulangerie : besoins en ingrédients, production du jour (ingrédients sortis du stock, produits finis entrés)
 * et bilan des invendus. Le stock ne bouge que par StockService, comme partout ailleurs.
 */
class ProductionService
{
    public function __construct(private StockService $stock) {}

    /**
     * Ingrédients nécessaires pour des quantités à produire, comparés au stock du point de vente.
     * Les ingrédients se consomment au prorata : 100 pièces avec une fournée de 40 = 2,5 fournées.
     *
     * @param  array<int, array{product_id: int, quantity: float|string}>  $lines
     * @return array{lines: array, ingredients: array, ok: bool}
     */
    public function needs(array $lines, int $branchId): array
    {
        $products = Product::with('recipeItems.ingredient')->whereIn('id', collect($lines)->pluck('product_id'))->get()->keyBy('id');
        $needs = [];
        $rows = [];

        foreach ($lines as $l) {
            $qty = round((float) $l['quantity'], 3);
            if ($qty <= 0) {
                continue;
            }
            $p = $products->get((int) $l['product_id']);
            if (! $p || ! $p->is_manufactured || (float) $p->batch_yield <= 0 || $p->recipeItems->isEmpty()) {
                abort(422, '« '.($p?->name ?? 'Produit').' » n\'a pas de recette complète (fournée et ingrédients).');
            }
            $batches = $qty / (float) $p->batch_yield;
            foreach ($p->recipeItems as $item) {
                $needs[$item->ingredient_id] = ($needs[$item->ingredient_id] ?? 0) + (float) $item->quantity * $batches;
            }
            $rows[] = ['product_id' => $p->id, 'name' => $p->name, 'quantity' => $qty, 'batches' => round($batches, 3)];
        }
        if ($rows === []) {
            abort(422, 'Saisissez au moins une quantité à produire.');
        }

        $ingredients = Product::whereIn('id', array_keys($needs))->get()->keyBy('id');
        $levels = StockLevel::where('branch_id', $branchId)->whereIn('product_id', array_keys($needs))->pluck('quantity', 'product_id');
        $list = [];
        foreach ($needs as $id => $needed) {
            $needed = round($needed, 3);
            $inStock = (float) ($levels[$id] ?? 0);
            $list[] = [
                'ingredient_id' => $id, 'name' => $ingredients[$id]->name, 'unit' => $ingredients[$id]->unit,
                'needed' => $needed, 'in_stock' => $inStock, 'missing' => round(max(0, $needed - $inStock), 3), 'ok' => $inStock + 0.0005 >= $needed,
            ];
        }

        return ['lines' => $rows, 'ingredients' => $list, 'ok' => collect($list)->every(fn ($i) => $i['ok'])];
    }

    /** Enregistre la production : tout ou rien. */
    public function produce(array $lines, ?int $userId, ?string $date, int $branchId): Production
    {
        return DB::transaction(function () use ($lines, $userId, $date, $branchId) {
            $plan = $this->needs($lines, $branchId);
            if (! $plan['ok']) {
                $missing = collect($plan['ingredients'])->where('ok', false)
                    ->map(fn ($i) => "{$i['name']} (manque ".rtrim(rtrim(number_format($i['missing'], 3, ',', ''), '0'), ',')." {$i['unit']})")->implode(', ');
                abort(422, "Stock d'ingrédients insuffisant : $missing.");
            }

            $production = Production::create(['produced_on' => $date ?: now()->toDateString(), 'branch_id' => $branchId, 'user_id' => $userId]);
            $reason = "Production n°{$production->id}";
            $products = Product::with('recipeItems.ingredient')->whereIn('id', collect($plan['lines'])->pluck('product_id'))->get()->keyBy('id');

            foreach ($plan['ingredients'] as $i) {
                $this->stock->move($i['ingredient_id'], 'out', $i['needed'], $reason, $userId, $branchId);
            }
            foreach ($plan['lines'] as $row) {
                $p = $products[$row['product_id']];
                $production->lines()->create([
                    'product_id' => $p->id, 'quantity' => $row['quantity'], 'batches' => $row['batches'],
                    'unit_cost' => round($this->unitCost($p) ?? 0, 2),
                ]);
                $this->stock->move($p->id, 'in', $row['quantity'], $reason, $userId, $branchId);
            }

            return $production->load('lines');
        });
    }

    /** Coût d'une pièce : coût d'une fournée (prix d'achat des ingrédients) divisé par le rendement. null sans recette. */
    public function unitCost(Product $p): ?float
    {
        if (! $p->is_manufactured || (float) $p->batch_yield <= 0 || $p->recipeItems->isEmpty()) {
            return null;
        }
        $batch = $p->recipeItems->sum(fn ($i) => (float) $i->quantity * (float) ($i->ingredient?->purchase_price ?? 0));

        return $batch / (float) $p->batch_yield;
    }

    /**
     * Produits fabriqués avec ce qui est déjà produit ce jour-là et une quantité conseillée :
     * la moyenne des ventes des 4 mêmes jours de la semaine précédents (null sans historique).
     */
    public function plan(string $date, int $branchId): array
    {
        $day = CarbonImmutable::parse($date);
        $products = Product::where('is_manufactured', true)->where('is_active', true)->orderBy('name')->get();
        $produced = $this->producedOn($date, $branchId);
        $history = collect(range(1, 4))->map(fn ($w) => $day->subWeeks($w)->toDateString());
        $soldByDay = $this->soldByDay($history->all(), $branchId);

        return $products->map(function (Product $p) use ($produced, $history, $soldByDay) {
            $values = $history->map(fn ($d) => (float) ($soldByDay[$p->id][$d] ?? 0))->filter(fn ($v) => $v > 0);

            return [
                'id' => $p->id, 'name' => $p->name, 'unit' => $p->unit, 'batch_yield' => (float) $p->batch_yield,
                'produced' => (float) ($produced[$p->id] ?? 0),
                'suggested' => $values->isEmpty() ? null : (int) round($values->avg()),
            ];
        })->values()->all();
    }

    /**
     * Bilan d'une journée : produit, vendu, invendu (jamais négatif) et, si le bilan est validé, ce qui a été mis au rebut.
     *
     * @return array{rows: array, summary: array}
     */
    public function unsold(string $date, int $branchId): array
    {
        $products = Product::where('is_manufactured', true)->with('recipeItems.ingredient')->orderBy('name')->get();
        $produced = $this->producedOn($date, $branchId);
        $sold = $this->soldByDay([$date], $branchId);
        $done = DailyUnsold::where('day', $date)->where('branch_id', $branchId)->get()->keyBy('product_id');

        $rows = $products->map(function (Product $p) use ($produced, $sold, $done, $date) {
            $made = (float) ($produced[$p->id] ?? 0);
            $s = (float) ($sold[$p->id][$date] ?? 0);
            $record = $done->get($p->id);

            return [
                'product_id' => $p->id, 'name' => $p->name, 'unit' => $p->unit,
                'produced' => $made, 'sold' => $s, 'unsold' => round(max(0, $made - $s), 3),
                'unit_cost' => round($this->unitCost($p) ?? 0, 2),
                'validated' => (bool) $record, 'discarded' => (float) ($record->discarded ?? 0), 'loss' => (float) ($record->loss ?? 0),
            ];
        })->filter(fn ($r) => $r['produced'] > 0 || $r['sold'] > 0 || $r['validated'])->values()->all();

        $made = array_sum(array_column($rows, 'produced'));

        return ['rows' => $rows, 'summary' => [
            'produced' => $made,
            'unsold' => array_sum(array_column($rows, 'unsold')),
            'unsold_rate' => $made > 0 ? round(array_sum(array_column($rows, 'unsold')) / $made * 100, 1) : 0,
            'loss' => round(array_sum(array_column($rows, 'loss')), 2),
            'validated' => $rows !== [] && collect($rows)->every(fn ($r) => $r['validated']),
        ]];
    }

    /**
     * Valide le bilan : pour chaque produit, l'invendu est mis au rebut (sorti du stock) ou gardé. Une seule fois par jour et produit.
     *
     * @param  array<int, array{product_id: int, discard: bool}>  $items
     */
    public function validateUnsold(string $date, array $items, ?int $userId, int $branchId): void
    {
        DB::transaction(function () use ($date, $items, $userId, $branchId) {
            $report = collect($this->unsold($date, $branchId)['rows'])->keyBy('product_id');
            $label = CarbonImmutable::parse($date)->format('d/m/Y');

            foreach ($items as $item) {
                $row = $report->get((int) $item['product_id']);
                if (! $row) {
                    continue;
                }
                if ($row['validated']) {
                    abort(422, "Le bilan de « {$row['name']} » est déjà validé pour ce jour.");
                }
                $discard = ! empty($item['discard']) ? $row['unsold'] : 0.0;
                if ($discard > 0) {
                    $this->stock->move($row['product_id'], 'out', $discard, "Invendu du $label", $userId, $branchId);
                }
                DailyUnsold::create([
                    'day' => $date, 'branch_id' => $branchId, 'product_id' => $row['product_id'],
                    'produced' => $row['produced'], 'sold' => $row['sold'], 'unsold' => $row['unsold'],
                    'discarded' => $discard, 'loss' => round($discard * $row['unit_cost'], 2), 'user_id' => $userId,
                ]);
            }
        });
    }

    /** @return array<int, float> produit => pièces produites ce jour-là dans le point de vente */
    private function producedOn(string $date, int $branchId): array
    {
        return ProductionLine::query()->join('productions', 'productions.id', '=', 'production_lines.production_id')
            ->where('productions.produced_on', $date)->where('productions.branch_id', $branchId)
            ->groupBy('production_lines.product_id')->selectRaw('production_lines.product_id as pid, sum(production_lines.quantity) as qty')
            ->pluck('qty', 'pid')->map(fn ($v) => (float) $v)->all();
    }

    /** @return array<int, array<string, float>> produit => [jour => quantité vendue en unité de base] (factures émises du point de vente) */
    private function soldByDay(array $days, int $branchId): array
    {
        $out = [];
        $rows = DB::table('sales_document_lines as l')->join('sales_documents as d', 'd.id', '=', 'l.sales_document_id')
            ->where('d.type', 'invoice')->whereIn('d.status', ['issued', 'partial', 'paid'])->where('d.branch_id', $branchId)
            ->whereIn('d.issue_date', $days)->whereNotNull('l.product_id')
            ->groupBy('l.product_id', 'd.issue_date')
            ->selectRaw('l.product_id as pid, d.issue_date as day, sum(l.quantity * l.unit_factor) as qty')->get();
        foreach ($rows as $r) {
            $out[$r->pid][CarbonImmutable::parse($r->day)->toDateString()] = (float) $r->qty;
        }

        return $out;
    }
}
