<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

/** Échéances : qui doit payer quand, retards, essais et abonnements qui se terminent. */
class DueController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['filter' => ['nullable', 'in:overdue,soon,trial,expired,all']]);
        $filter = $request->query('filter', 'all');

        $query = Subscription::with(['tenant', 'plan'])->whereHas('tenant');

        match ($filter) {
            'overdue' => $query->where('status', 'past_due'),
            'soon' => $query->whereIn('status', ['trial', 'active'])->where(function ($q) {
                $q->where(fn ($t) => $t->where('status', 'trial')->whereBetween('trial_ends_at', [now(), now()->addDays(7)]))
                    ->orWhere(fn ($t) => $t->where('status', 'active')->whereBetween('current_period_end', [now(), now()->addDays(7)]));
            }),
            'trial' => $query->where('status', 'trial'),
            'expired' => $query->whereIn('status', ['expired', 'cancelled']),
            default => null,
        };

        // Les plus urgents d'abord : échéance la plus proche en premier.
        $query->orderByRaw('coalesce(current_period_end, trial_ends_at) asc');

        return view('console.dues.index', [
            'subs' => $query->paginate(25)->withQueryString(),
            'filter' => $filter,
            'counts' => [
                'overdue' => Subscription::where('status', 'past_due')->count(),
                'trial' => Subscription::where('status', 'trial')->count(),
                'expired' => Subscription::whereIn('status', ['expired', 'cancelled'])->count(),
            ],
        ]);
    }
}
