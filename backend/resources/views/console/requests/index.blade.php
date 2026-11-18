@extends('console.layout')
@section('title', 'Demandes d\'inscription')
@php
    $tone = ['new' => 'pill-info', 'contacted' => 'pill-warn', 'converted' => 'pill-ok', 'rejected' => ''];
@endphp
@section('content')
    <header class="page-head">
        <div><h1>Demandes d'inscription</h1><p class="muted">Prospects qui ont demandé un accès depuis le site. {{ $requests->total() }} demande(s).</p></div>
    </header>

    <div class="toolbar">
        <a class="btn {{ request('status') ? 'btn-secondary' : '' }} btn-sm" href="{{ route('console.requests.index') }}">Toutes</a>
        @foreach(\App\Models\SignupRequest::STATUSES as $k => $l)
            <a class="btn {{ request('status') === $k ? '' : 'btn-secondary' }} btn-sm" href="{{ route('console.requests.index', ['status' => $k]) }}">{{ $l }} ({{ $counts[$k] ?? 0 }})</a>
        @endforeach
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Entreprise</th><th>Contact</th><th>Message</th><th>Reçue le</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($requests as $r)
                <tr>
                    <td><strong>{{ $r->company }}</strong>@if($r->country)<div class="muted small">{{ $r->country }}</div>@endif</td>
                    <td>{{ $r->name }}<div class="muted small">{{ $r->email }}@if($r->phone) · {{ $r->phone }}@endif</div></td>
                    <td style="max-width:280px">{{ \Illuminate\Support\Str::limit($r->message, 140) ?: '—' }}</td>
                    <td>{{ $r->created_at->format('d/m/Y H:i') }}</td>
                    <td><span class="pill {{ $tone[$r->status] }}">{{ \App\Models\SignupRequest::STATUSES[$r->status] }}</span>
                        @if($r->tenant_id)<div class="small"><a href="{{ route('console.tenants.show', $r->tenant_id) }}">{{ $r->tenant_id }}</a></div>@endif</td>
                    <td>
                        <div class="actions-row">
                            @if($r->status !== 'converted')
                                @if($admin->can('tenants.create'))<a class="btn btn-sm" href="{{ route('console.tenants.create', ['request' => $r->id]) }}">Créer l'entreprise</a>@endif
                                @if($r->status === 'new')
                                    <form method="POST" action="{{ route('console.requests.update', $r) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="contacted"><button class="btn btn-secondary btn-sm">Contactée</button></form>
                                @endif
                                @if($r->status !== 'rejected')
                                    <form method="POST" action="{{ route('console.requests.update', $r) }}" onsubmit="return confirm('Rejeter cette demande ?')">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><button class="btn btn-danger btn-sm">Rejeter</button></form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Aucune demande.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('console._pager', ['paginator' => $requests])
@endsection
