<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\CurrentBranch;
use App\Services\NumberSequence;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transferts de stock du siège vers les points de vente : demandé -> expédié -> reçu (ou annulé tant qu'il n'est pas expédié).
 * Le stock sort du siège à l'expédition et entre dans le point de vente à la réception ; un écart de réception reste visible.
 */
class StockTransferController extends Controller
{
    public function __construct(private StockService $stock, private NumberSequence $numbers, private CurrentBranch $current) {}

    /** Un utilisateur de point de vente ne voit que les transferts qui le concernent. */
    private function visible()
    {
        $q = StockTransfer::query();
        if ($this->current->isRestricted()) {
            $b = (int) auth()->user()->branch_id;
            $q->where(fn ($w) => $w->where('to_branch_id', $b)->orWhere('from_branch_id', $b));
        } elseif ($b = $this->current->id()) {
            $q->where(fn ($w) => $w->where('to_branch_id', $b)->orWhere('from_branch_id', $b));
        }

        return $q;
    }

    private function find(int $id): StockTransfer
    {
        return $this->visible()->with(['lines.product', 'from:id,name', 'to:id,name'])->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:requested,shipped,received,cancelled']]);
        $q = $this->visible()->with(['from:id,name', 'to:id,name'])->withCount('lines')->latest('id');
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return response()->json($q->paginate($request->integer('per_page', 20)));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->find($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ]);

        $main = $this->stock->mainBranchId();
        // Un point de vente ne peut demander que pour lui-même ; le siège choisit la destination.
        $to = $this->current->isRestricted() ? (int) $request->user()->branch_id : (int) ($data['to_branch_id'] ?? 0);
        if ($to === 0 || $to === $main) {
            throw ValidationException::withMessages(['to_branch_id' => ['Choisissez un point de vente de destination.']]);
        }
        abort_unless(Branch::where('id', $to)->where('is_active', true)->exists(), 422, 'Point de vente de destination inactif.');

        $transfer = DB::transaction(function () use ($data, $main, $to, $request) {
            $t = StockTransfer::create([
                'number' => $this->numbers->next('transfer'), 'from_branch_id' => $main, 'to_branch_id' => $to,
                'status' => 'requested', 'note' => $data['note'] ?? null, 'requested_by' => $request->user()->id,
            ]);
            foreach ($data['lines'] as $l) {
                $t->lines()->create(['product_id' => $l['product_id'], 'quantity' => $l['quantity']]);
            }

            return $t;
        });

        return response()->json($this->find($transfer->id), 201);
    }

    /** Le siège expédie : le stock est retiré du siège (refus si insuffisant, rien n'est modifié). */
    public function ship(Request $request, int $id): JsonResponse
    {
        abort_if($this->current->isRestricted(), 403, 'Seul le siège expédie un transfert.');

        DB::transaction(function () use ($id, $request) {
            $t = StockTransfer::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($t->status !== 'requested', 422, 'Seul un transfert « demandé » peut être expédié.');

            foreach ($t->lines as $line) {
                try {
                    $movement = $this->stock->move($line->product_id, 'transfer_out', (float) $line->quantity, "Transfert {$t->number}", $request->user()->id, $t->from_branch_id);
                    $line->update(['batches' => $movement->batches]);
                } catch (ValidationException $e) {
                    $name = Product::find($line->product_id)?->name ?? 'Produit';
                    abort(422, "« {$name} » : ".($e->errors()['quantity'][0] ?? 'stock insuffisant.'));
                }
            }
            $t->update(['status' => 'shipped', 'shipped_by' => $request->user()->id, 'shipped_at' => now()]);
        });

        return response()->json($this->find($id));
    }

    /** Le point de vente réceptionne ; les quantités reçues peuvent différer des quantités expédiées. */
    public function receive(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.received_quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        DB::transaction(function () use ($id, $data, $request) {
            $t = $this->visible()->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($t->status !== 'shipped', 422, 'Seul un transfert « expédié » peut être réceptionné.');
            if ($this->current->isRestricted()) {
                abort_unless((int) $request->user()->branch_id === (int) $t->to_branch_id, 403, 'Seul le point de vente destinataire réceptionne.');
            }

            $received = collect($data['lines'] ?? [])->pluck('received_quantity', 'id');
            foreach ($t->lines as $line) {
                $qty = round((float) ($received[$line->id] ?? $line->quantity), 3);
                abort_if($qty > (float) $line->quantity, 422, 'La quantité reçue ne peut pas dépasser la quantité expédiée.');
                $line->update(['received_quantity' => $qty]);
                if ($qty > 0) {
                    $this->stock->move($line->product_id, 'transfer_in', $qty, "Transfert {$t->number}", $request->user()->id, $t->to_branch_id, batches: $this->shareOfBatches($line->batches, $qty, (float) $line->quantity));
                }
            }
            $t->update(['status' => 'received', 'received_by' => $request->user()->id, 'received_at' => now()]);
        });

        return response()->json($this->find($id));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        DB::transaction(function () use ($id) {
            $t = $this->visible()->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($t->status !== 'requested', 422, 'Seul un transfert « demandé » peut être annulé.');
            $t->update(['status' => 'cancelled']);
        });

        return response()->json($this->find($id));
    }

    /** Lots à recréer à la réception : tout si la quantité reçue égale l'expédiée, sinon au prorata des lots expédiés (premiers lots d'abord). */
    private function shareOfBatches(?array $batches, float $received, float $shipped): ?array
    {
        if (! $batches) {
            return null;
        }
        $left = round($received, 3);
        $out = [];
        foreach ($batches as $b) {
            if ($left <= 0) {
                break;
            }
            $q = min($left, (float) $b['quantity']);
            $out[] = [...$b, 'quantity' => round($q, 3)];
            $left = round($left - $q, 3);
        }

        return $out;
    }
}
