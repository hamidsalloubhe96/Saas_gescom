<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Category::withCount('products')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:categories,name']]);

        return response()->json(Category::create($data)->loadCount('products'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->update($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($id)],
        ]));

        return response()->json($category->loadCount('products'));
    }

    /** Les produits de la catégorie ne sont pas supprimés : leur catégorie passe à « aucune ». */
    public function destroy(int $id): JsonResponse
    {
        Category::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
