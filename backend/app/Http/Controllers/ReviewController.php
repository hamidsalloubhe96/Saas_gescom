<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Avis laissé par un utilisateur d'une entreprise ; relu dans la console avant toute publication. */
class ReviewController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'min:10', 'max:1000'],
        ], ['body.min' => 'Votre avis est un peu court : écrivez au moins 10 caractères.']);

        $tenant = tenant();
        if (Review::where('tenant_id', $tenant->id)->where('created_at', '>=', now()->subDays(30))->count() >= 3) {
            abort(422, 'Vous avez déjà envoyé plusieurs avis ce mois-ci. Merci !');
        }

        Review::create($data + [
            'tenant_id' => $tenant->id,
            'author_name' => $request->user()->name,
            'company' => $tenant->name,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Merci pour votre avis !'], 201);
    }
}
