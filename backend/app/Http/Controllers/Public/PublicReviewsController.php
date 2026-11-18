<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

/** Avis publiés, pour la vitrine du site. Ne renvoie jamais l'identifiant de l'entreprise ni son e-mail. */
class PublicReviewsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Review::where('status', 'published')->latest('published_at')->limit(30)
                ->get(['author_name', 'company', 'rating', 'body', 'published_at']),
        )->header('Cache-Control', 'public, max-age=300');
    }
}
