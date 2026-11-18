<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tickets de support vus par l'entreprise : ses propres tickets uniquement. */
class SupportController extends Controller
{
    private function own(int $id): SupportTicket
    {
        return SupportTicket::where('tenant_id', tenant()->id)->findOrFail($id);
    }

    public function index(): JsonResponse
    {
        return response()->json(SupportTicket::where('tenant_id', tenant()->id)->latest('last_message_at')->limit(50)->get());
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->own($id)->load('messages'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['subject' => ['required', 'string', 'min:3', 'max:160'], 'body' => ['required', 'string', 'min:5', 'max:3000']]);
        $tenant = tenant();
        abort_if(SupportTicket::where('tenant_id', $tenant->id)->where('status', '!=', 'closed')->count() >= 5, 422, 'Vous avez déjà 5 tickets en cours. Complétez-en un ou attendez notre réponse.');

        $ticket = SupportTicket::create([
            'tenant_id' => $tenant->id, 'author_name' => $request->user()->name, 'author_email' => $request->user()->email,
            'subject' => $data['subject'], 'status' => 'open', 'last_message_at' => now(),
        ]);
        SupportMessage::create(['ticket_id' => $ticket->id, 'author_type' => 'tenant', 'author_name' => $request->user()->name, 'body' => $data['body']]);

        return response()->json($ticket->load('messages'), 201);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $ticket = $this->own($id);
        abort_if($ticket->status === 'closed', 422, 'Ce ticket est clos. Ouvrez-en un nouveau.');
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:3000']]);

        SupportMessage::create(['ticket_id' => $ticket->id, 'author_type' => 'tenant', 'author_name' => $request->user()->name, 'body' => $data['body']]);
        $ticket->update(['status' => 'open', 'last_message_at' => now()]);

        return response()->json($ticket->load('messages'));
    }
}
