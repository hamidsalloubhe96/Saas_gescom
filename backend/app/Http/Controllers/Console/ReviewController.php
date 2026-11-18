<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Avis des utilisateurs : à relire, puis publier ou masquer. */
class ReviewController extends Controller
{
    public function index()
    {
        return view('console.reviews.index', [
            'pending' => Review::where('status', 'pending')->oldest('id')->get(),
            'handled' => Review::where('status', '!=', 'pending')->latest('id')->paginate(15),
        ]);
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:published,hidden,pending']]);

        $review->update([
            'status' => $data['status'], 'handled_by' => $request->user('admin')->id,
            'published_at' => $data['status'] === 'published' ? ($review->published_at ?? now()) : null,
        ]);
        AuditLog::record('review.'.$data['status'], "Avis de {$review->author_name} ({$review->company}) : {$data['status']}", $review->tenant_id, ['review' => $review->id]);

        return back()->with('success', $data['status'] === 'published' ? 'Avis publié.' : ($data['status'] === 'hidden' ? 'Avis masqué.' : 'Avis remis à relire.'));
    }
}
