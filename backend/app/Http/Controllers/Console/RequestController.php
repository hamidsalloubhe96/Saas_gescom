<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SignupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Demandes d'accès reçues depuis le site public. */
class RequestController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'in:new,contacted,converted,rejected']]);

        $query = SignupRequest::query()->latest('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return view('console.requests.index', [
            'requests' => $query->paginate(20)->withQueryString(),
            'counts' => SignupRequest::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function update(Request $request, SignupRequest $signupRequest): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:contacted,rejected,new']]);

        $signupRequest->update(['status' => $data['status'], 'handled_at' => now(), 'handled_by' => $request->user('admin')->id]);
        AuditLog::record('request.update', "Demande de {$signupRequest->company} : {$data['status']}", null, ['request' => $signupRequest->id]);

        return back()->with('success', 'Demande mise à jour.');
    }
}
