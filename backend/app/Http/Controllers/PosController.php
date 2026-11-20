<?php

namespace App\Http\Controllers;

use App\Models\RegisterSession;
use App\Models\SalesDocument;
use App\Services\DocumentService;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function __construct(private PosService $pos, private DocumentService $documents) {}

    private function present(RegisterSession $s): array
    {
        $s->loadMissing('opener:id,name', 'closer:id,name');

        return $s->toArray() + ['summary' => $this->pos->summary($s)];
    }

    /** Session ouverte (ou null) avec ses chiffres en direct. */
    public function session(): JsonResponse
    {
        $s = $this->pos->current();

        return response()->json(['session' => $s ? $this->present($s) : null]);
    }

    public function open(Request $request): JsonResponse
    {
        $data = $request->validate(['opening_amount' => ['required', 'numeric', 'min:0', 'max:999999999999']]);

        return response()->json(
            $this->present($this->pos->open((float) $data['opening_amount'], $request->user()->id)),
            201,
        );
    }

    public function close(Request $request): JsonResponse
    {
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = $this->pos->close($this->pos->requireOpen(), (float) $data['counted_cash'], $data['notes'] ?? null, $request->user()->id);

        return response()->json($this->present($session));
    }

    public function sessions(Request $request): JsonResponse
    {
        $paginator = RegisterSession::with('opener:id,name', 'closer:id,name')
            ->latest('id')->paginate($request->integer('per_page', 10));

        return response()->json($paginator);
    }

    /** Ventes de la session ouverte (réservé à la caisse : pas besoin du droit « consulter les factures »). */
    public function sales(): JsonResponse
    {
        $session = $this->pos->requireOpen();

        return response()->json(
            SalesDocument::where('channel', 'pos')->where('register_session_id', $session->id)
                ->with('customer:id,name')->latest('id')->paginate(100),
        );
    }

    /** Détail d'une vente comptoir (pour le ticket). */
    public function showSale(int $id): JsonResponse
    {
        return response()->json($this->documents->load(SalesDocument::where('channel', 'pos')->findOrFail($id)));
    }

    public function sale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'lines.*.unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'lines.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payments' => ['nullable', 'array', 'max:10'],
            'payments.*.method' => ['required', 'in:cash,card,transfer,cheque,other'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
        ]);

        return response()->json($this->pos->sale($data, $request->user()->id), 201);
    }

    public function void(Request $request, int $id): JsonResponse
    {
        return response()->json($this->pos->void($id, $request->user()->id));
    }
}
