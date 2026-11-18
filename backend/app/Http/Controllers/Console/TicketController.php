<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Tickets de support : répondre, clore, rouvrir. */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'in:open,answered,closed']]);
        $q = SupportTicket::with('tenant')->latest('last_message_at');
        $q->when($request->query('status'), fn ($x, $s) => $x->where('status', $s), fn ($x) => $x->where('status', '!=', 'closed'));

        return view('console.tickets.index', ['tickets' => $q->paginate(20)->withQueryString(), 'status' => $request->query('status')]);
    }

    public function show(SupportTicket $ticket)
    {
        return view('console.tickets.show', ['ticket' => $ticket->load(['messages', 'tenant'])]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:3000']]);
        $admin = $request->user('admin');
        SupportMessage::create(['ticket_id' => $ticket->id, 'author_type' => 'admin', 'author_name' => $admin->name, 'body' => $data['body']]);
        $ticket->update(['status' => 'answered', 'last_message_at' => now()]);
        AuditLog::record('ticket.reply', "Réponse au ticket #{$ticket->id} « {$ticket->subject} »", $ticket->tenant_id, ['ticket' => $ticket->id]);

        return back()->with('success', 'Réponse envoyée.');
    }

    public function status(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:open,closed']]);
        $ticket->update(['status' => $data['status']]);
        AuditLog::record('ticket.'.$data['status'], "Ticket #{$ticket->id} : {$data['status']}", $ticket->tenant_id, ['ticket' => $ticket->id]);

        return back()->with('success', $data['status'] === 'closed' ? 'Ticket clos.' : 'Ticket rouvert.');
    }
}
