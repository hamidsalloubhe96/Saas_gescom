<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    private function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            'supplier_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = PurchaseOrder::query()->with('supplier:id,name')->latest('id');

        foreach (['status', 'supplier_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(fn ($q) => $q
                ->where('number', 'ilike', $like)
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'ilike', $like)));
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(
            $this->purchases->save(null, $request->validate($this->rules()), $request->user()->id),
            201,
        );
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->purchases->load(PurchaseOrder::findOrFail($id)));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->purchases->save(PurchaseOrder::findOrFail($id), $request->validate($this->rules()), $request->user()->id),
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->purchases->delete(PurchaseOrder::findOrFail($id));

        return response()->json(null, 204);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:order,cancel,close']]);

        return response()->json($this->purchases->transition(PurchaseOrder::findOrFail($id), $data['action']));
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.line_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.batch_number' => ['nullable', 'string', 'max:60'],
            'items.*.expires_at' => ['nullable', 'date'],
        ]);

        return response()->json(
            $this->purchases->receive(PurchaseOrder::findOrFail($id), $data['items'], $request->user()->id),
        );
    }

    public function addPayment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'method' => ['required', 'in:cash,card,transfer,cheque,other'],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json(
            $this->purchases->addPayment(PurchaseOrder::findOrFail($id), $data, $request->user()->id),
            201,
        );
    }

    public function removePayment(int $id, int $paymentId): JsonResponse
    {
        return response()->json($this->purchases->removePayment(PurchaseOrder::findOrFail($id), $paymentId));
    }
}
