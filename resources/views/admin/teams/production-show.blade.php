@extends('admin.layout')

@section('title', $team->name)
@section('page-title', 'My Team')
@section('page-icon', 'people-fill')

@section('content')
<style>
    .pt-num { font-variant-numeric: tabular-nums; }
    .pt-zero { opacity: .35; }
    .pt-activity li { font-size: 13px; padding: 7px 0; border-bottom: 1px solid var(--bs-border-color, rgba(0,0,0,.08)); }
    .pt-activity li:last-child { border-bottom: 0; }
    .pt-activity .when { font-size: 11px; opacity: .65; }
</style>

<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div>
        <h5 class="mb-0">{{ $team->name }}</h5>
        <div class="small text-muted">
            Team head: {{ $team->teamHead?->name ?? '—' }} · {{ $report->count() }} members
        </div>
    </div>
    <div class="btn-group btn-group-sm" role="group" aria-label="Period">
        @foreach($periods as $key => [$label])
            <a href="{{ route('admin.teams.show', [$team, 'period' => $key]) }}"
               class="btn {{ $period === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        Team work report <span class="text-muted fw-normal small">· {{ $periodLabel }} · Trello work done through the CRM</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr class="small text-muted">
                    <th>Member</th>
                    <th class="text-end" title="Cards created">Created</th>
                    <th class="text-end" title="Cards moved to another list">Moved</th>
                    <th class="text-end" title="Cards + checklist items completed">Completed</th>
                    <th class="text-end">Comments</th>
                    <th class="text-end" title="Attachments added">Files</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Last active</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report as $row)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $row['user']->name }}</span>
                            @if($row['is_head'])<span class="badge bg-primary-subtle text-primary ms-1">Head</span>@endif
                            <div class="small text-muted">{{ $row['user']->role?->name }}</div>
                        </td>
                        @foreach(['created', 'moved', 'completed', 'comments', 'files'] as $col)
                            <td class="text-end pt-num {{ $row[$col] ? '' : 'pt-zero' }}">{{ $row[$col] }}</td>
                        @endforeach
                        <td class="text-end pt-num fw-semibold {{ $row['total'] ? '' : 'pt-zero' }}">{{ $row['total'] }}</td>
                        <td class="text-end small text-muted">
                            {{ $row['last_at'] ? \Illuminate\Support\Carbon::parse($row['last_at'])->diffForHumans() : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted small">Is team mein abhi koi member nahi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent small text-muted">
        Sirf CRM se kiya gaya kaam ginna jata hai. Seedha trello.com par shared account se kiya gaya kaam yahan nahi aata.
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">Recent team activity</div>
    <div class="card-body">
        @if($recent->isEmpty())
            <div class="text-muted small">{{ $periodLabel }} mein koi Trello activity nahi.</div>
        @else
            <ul class="list-unstyled mb-0 pt-activity">
                @foreach($recent as $log)
                    <li><strong>{{ $log->user?->name ?? 'Someone' }}</strong> {{ $log->description }}
                        <div class="when">{{ $log->occurred_at->diffForHumans() }}@if(!empty($log->meta['board_name'])) · {{ $log->meta['board_name'] }}@endif</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
