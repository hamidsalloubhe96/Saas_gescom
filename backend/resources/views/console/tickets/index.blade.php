@extends('console.layout')
@section('title', 'Support')
@section('content')
    <header class="page-head">
        <div><h1>Support</h1><p class="muted">Tickets envoyés par les entreprises. Par défaut, seuls les tickets non clos sont affichés.</p></div>
        <form method="GET" class="actions-row">
            <select name="status" aria-label="Statut" onchange="this.form.submit()">
                <option value="">En cours</option>
                @foreach(\App\Models\SupportTicket::STATUSES as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach
            </select>
        </form>
    </header>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Sujet</th><th>Entreprise</th><th>Statut</th><th>Dernier message</th></tr></thead>
            <tbody>
            @forelse($tickets as $t)
                <tr>
                    <td><a href="{{ route('console.tickets.show', $t) }}"><strong>{{ $t->subject }}</strong></a><div class="muted small">#{{ $t->id }} · {{ $t->author_name }}</div></td>
                    <td>{{ $t->tenant?->name ?? $t->tenant_id }}</td>
                    <td><span class="pill {{ $t->status === 'open' ? 'pill-warn' : ($t->status === 'answered' ? 'pill-ok' : '') }}">{{ \App\Models\SupportTicket::STATUSES[$t->status] }}</span></td>
                    <td>{{ $t->last_message_at?->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">Aucun ticket.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('console._pager', ['paginator' => $tickets])
@endsection
