<?php

namespace App\Http\Controllers;

use App\Services\CurrentBranch;
use App\Services\ProductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Boulangerie : production du jour (besoins, enregistrement) et bilan des invendus. */
class ProductionController extends Controller
{
    public function __construct(private ProductionService $production, private CurrentBranch $branch) {}

    private function date(Request $request): string
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return $request->input('date', now()->toDateString());
    }

    private function lineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    /** Produits fabriqués, ce qui est déjà produit à la date donnée et la quantité conseillée. */
    public function plan(Request $request): JsonResponse
    {
        $date = $this->date($request);

        return response()->json(['date' => $date, 'products' => $this->production->plan($date, $this->branch->forWrite())]);
    }

    /** Ingrédients nécessaires et état du stock, sans rien enregistrer. */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate($this->lineRules());

        return response()->json($this->production->needs($data['lines'], $this->branch->forWrite()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->lineRules() + ['date' => ['nullable', 'date_format:Y-m-d']]);
        $production = $this->production->produce($data['lines'], $request->user()->id, $data['date'] ?? null, $this->branch->forWrite());

        return response()->json($production, 201);
    }

    public function unsold(Request $request): JsonResponse
    {
        return response()->json($this->production->unsold($this->date($request), $this->branch->forWrite()));
    }

    public function validateUnsold(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.discard' => ['required', 'boolean'],
        ]);
        $branch = $this->branch->forWrite();
        $this->production->validateUnsold($data['date'], $data['items'], $request->user()->id, $branch);

        return response()->json($this->production->unsold($data['date'], $branch));
    }
}
