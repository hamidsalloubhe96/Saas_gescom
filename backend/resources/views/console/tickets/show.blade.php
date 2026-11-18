@extends('console.layout')
@section('title', 'Ticket #'.$ticket->id)
@section('content')
    <header class="page-head">
        <div><h1>{{ $ticket->subject }}</h1>
            <p class="muted">#{{ $ticket->id }} · {{ $ticket->tenant?->name ?? $ticket->tenant_id }} · {{ $ticket->author_name }} {{ $ticket->author_email }} · {{ \App\Models\SupportTicket::STATUSES[$ticket->status] }}</p></div>
        <a href="{{ route('console.tickets.index') }}" class="btn btn-secondary">← Tickets</a>
    </header>
    <section class="panel">
        @foreach($ticket->messages as $m)
            <div style="padding:.7rem 0;border-bottom:1px solid var(--border)">
                <strong>{{ $m->author_name }}</strong> <span class="pill {{ $m->author_type === 'admin' ? 'pill-ok' : '' }}">{{ $m->author_type === 'admin' ? 'Équipe' : 'Client' }}</span>
                <span class="muted small">{{ $m->created_at->format('d/m/Y H:i') }}</span>
                <p style="margin:.35rem 0;white-space:pre-line">{{ $m->body }}</p>
            </div>
        @endforeach
    </section>
    @if($ticket->status !== 'closed')
    <section class="panel">
        <h2>Répondre</h2>
        <form method="POST" action="{{ route('console.tickets.reply', $ticket) }}">@csrf
            <div class="field"><label for="body">Message</label><textarea id="body" name="body" rows="5" required minlength="2" maxlength="3000">{{ old('body') }}</textarea>
                @error('body')<p class="field-error">{{ $message }}</p>@enderror</div>
            <button class="btn">Envoyer la réponse</button>
        </form>
    </section>
    @endif
    <form method="POST" action="{{ route('console.tickets.status', $ticket) }}" style="margin-top:1rem">@csrf @method('PATCH')
        <input type="hidden" name="status" value="{{ $ticket->status === 'closed' ? 'open' : 'closed' }}">
        <button class="btn btn-secondary">{{ $ticket->status === 'closed' ? 'Rouvrir le ticket' : 'Clore le ticket' }}</button>
    </form>
@endsection
