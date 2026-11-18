<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD commun aux tiers (clients, fournisseurs). Les sous-classes
 * indiquent le modèle, la table et leurs règles spécifiques.
 */
abstract class PartnerController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    abstract protected function table(): string;

    /** Règles de validation propres au type de tiers. */
    protected function extraRules(): array
    {
        return [];
    }

    private function rules(?int $ignoreId = null): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190', Rule::unique($this->table(), 'email')->ignore($ignoreId)],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->extraRules());
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'in:0,1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $this->model()::query()->orderBy('name');

        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhere('phone', 'ilike', $like)
                ->orWhere('city', 'ilike', $like));
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $item = $this->model()::create($request->validate($this->rules()));

        return response()->json($item->refresh(), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->model()::findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->model()::findOrFail($id);
        $item->update($request->validate($this->rules($id)));

        return response()->json($item->refresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->model()::findOrFail($id);

        try {
            $item->delete();
        } catch (QueryException $e) {
            // 23503 / 23001 = violation de clé étrangère ou RESTRICT (des documents référencent ce tiers)
            if (in_array($e->getCode(), ['23503', '23001'], true)) {
                abort(409, 'Suppression impossible : des documents sont liés à cette fiche. Désactivez-la plutôt.');
            }
            throw $e;
        }

        return response()->json(null, 204);
    }
}
