<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LegalText;
use Illuminate\Http\JsonResponse;

/** Textes légaux en vigueur (dernière version publiée) : pages publiques et liens de pied de page. */
class PublicLegalController extends Controller
{
    public function index(): JsonResponse
    {
        $out = [];
        foreach (LegalText::KEYS as $key => $label) {
            $current = LegalText::current($key);
            $out[] = ['key' => $key, 'title' => $current?->title ?? $label, 'available' => (bool) $current];
        }

        return response()->json($out)->header('Cache-Control', 'public, max-age=300');
    }

    public function show(string $key): JsonResponse
    {
        abort_unless(array_key_exists($key, LegalText::KEYS), 404);
        $text = LegalText::current($key);
        abort_unless($text, 404, 'Ce texte n\'est pas encore publié.');

        return response()->json([
            'key' => $key, 'title' => $text->title, 'body' => $text->body, 'version' => $text->version, 'published_at' => $text->published_at,
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
