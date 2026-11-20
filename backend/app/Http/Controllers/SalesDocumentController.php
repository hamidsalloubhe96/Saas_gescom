<?php

namespace App\Http\Controllers;

use App\Models\SalesDocument;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesDocumentController extends Controller
{
    public function __construct(private DocumentService $documents) {}

    private function rules(bool $creating): array
    {
        return array_filter([
            'type' => $creating ? ['required', 'in:quote,invoice'] : null,
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'in:quote,invoice'],
            'channel' => ['nullable', 'in:back_office,pos'],
            'register_session_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:20'],
            'customer_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = SalesDocument::query()->with('customer:id,name')->latest('id');

        foreach (['type', 'channel', 'register_session_id', 'status', 'customer_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(fn ($q) => $q
                ->where('number', 'ilike', $like)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', $like)));
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $doc = $this->documents->save(null, $request->validate($this->rules(true)), $request->user()->id);

        return response()->json($doc, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->documents->load(SalesDocument::findOrFail($id)));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $doc = SalesDocument::findOrFail($id);

        return response()->json($this->documents->save($doc, $request->validate($this->rules(false)), $request->user()->id));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->documents->delete(SalesDocument::findOrFail($id));

        return response()->json(null, 204);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:send,accept,reject,reopen,issue,cancel']]);

        return response()->json(
            $this->documents->transition(SalesDocument::findOrFail($id), $data['action'], $request->user()->id),
        );
    }

    public function convert(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->documents->convert(SalesDocument::findOrFail($id), $request->user()->id),
            201,
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
            $this->documents->addPayment(SalesDocument::findOrFail($id), $data, $request->user()->id),
            201,
        );
    }

    public function removePayment(int $id, int $paymentId): JsonResponse
    {
        return response()->json($this->documents->removePayment(SalesDocument::findOrFail($id), $paymentId));
    }
}
