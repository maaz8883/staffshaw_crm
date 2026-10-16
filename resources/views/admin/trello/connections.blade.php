@extends('admin.layout')

@section('title', 'Trello Connections')
@section('page-title', 'Trello Connections')
@section('page-icon', 'diagram-3')

@section('content')
<div class="mb-3 d-flex gap-2 flex-wrap">
    <a href="{{ route('admin.trello.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Boards
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card">
    <div class="card-body">
        <p class="text-muted small mb-3">
            Har parent team ke liye alag Trello API key + token.
            Staffshaw → <strong>sales.trello@staffshaw.com</strong>,
            Nextbyte → <strong>sales.nexbyte@staffshaw.com</strong>.
        </p>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>Company</th>
                        <th>Trello account</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($teams as $team)
                        @php $integ = $team->trelloIntegration; @endphp
                        <tr>
                            <td class="fw-semibold">{{ \App\Support\TeamTrelloAccounts::label($team) }}</td>
                            <td>{{ $team->company?->name ?? '—' }}</td>
                            <td>
                                @if($integ?->isReady())
                                    {{ $integ->displayAccount() }}
                                    @if($integ->trello_username)
                                        <span class="text-muted small">({{ '@'.$integ->trello_username }})</span>
                                    @endif
                                @else
                                    <span class="text-muted">Not connected</span>
                                    @if(!empty($suggestedEmails[$team->id]))
                                        <div class="small text-muted">Use {{ $suggestedEmails[$team->id] }}</div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if($integ?->isReady())
                                    <span class="badge text-bg-success">Connected</span>
                                    @if($integ->last_verified_at)
                                        <div class="small text-muted">{{ $integ->last_verified_at->diffForHumans() }}</div>
                                    @endif
                                @else
                                    <span class="badge text-bg-secondary">Missing</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.trello.index', ['team' => $team->id]) }}" class="btn btn-sm btn-outline-secondary">Boards</a>
                                <a href="{{ route('admin.trello.edit', $team) }}" class="btn btn-sm btn-primary">
                                    {{ $integ?->isReady() ? 'Edit' : 'Connect' }}
                                </a>
                                @if($integ)
                                    <form action="{{ route('admin.trello.destroy', $team) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Disconnect Trello for {{ $team->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Disconnect</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
