<?php

namespace App\Http\Controllers;

use App\Models\ProductBatch;
use App\Services\CurrentBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Péremptions : lots périmés ou qui approchent de leur date limite, avec la valeur d'achat concernée. */
class ExpiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:730'], 'q' => ['nullable', 'string', 'max:100']]);
        $days = (int) ($data['days'] ?? 90);
        $branch = app(CurrentBranch::class)->id();
        $today = now()->startOfDay();

        $rows = ProductBatch::query()->where('quantity', '>', 0)->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $today->copy()->addDays($days))
            ->when($branch, fn ($q) => $q->where('branch_id', $branch))
            ->when(filled($data['q'] ?? null), fn ($q) => $q->whereHas('product', fn ($p) => $p->where('name', 'ilike', '%'.$data['q'].'%')))
            ->with('product:id,name,dosage,form,purchase_price,unit', 'branch:id,name')
            ->orderBy('expires_at')->limit(500)->get()
            ->map(function (ProductBatch $b) use ($today) {
                $left = (int) $today->diffInDays($b->expires_at->copy()->startOfDay(), false);

                return [
                    'id' => $b->id, 'product_id' => $b->product_id, 'product' => $b->product?->name,
                    'dosage' => $b->product?->dosage, 'form' => $b->product?->form, 'unit' => $b->product?->unit,
                    'branch' => $b->branch?->name, 'batch_number' => $b->batch_number,
                    'expires_at' => $b->expires_at->toDateString(), 'days_left' => $left,
                    'status' => $left < 0 ? 'expired' : ($left <= 30 ? 'soon' : 'watch'),
                    'quantity' => (float) $b->quantity,
                    'value' => round((float) $b->quantity * (float) ($b->product?->purchase_price ?? 0), 2),
                ];
            });

        return response()->json([
            'days' => $days,
            'summary' => [
                'expired' => $rows->where('status', 'expired')->count(),
                'expired_value' => round($rows->where('status', 'expired')->sum('value'), 2),
                'soon' => $rows->where('status', 'soon')->count(),
                'watch' => $rows->where('status', 'watch')->count(),
            ],
            'data' => $rows->values(),
        ]);
    }
}
