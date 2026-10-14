@extends('admin.layout')

@section('title', 'Lead Details')
@section('page-title', 'Lead Details')
@section('page-icon', 'funnel')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.leads.edit', $lead) }}" class="btn btn-outline-warning btn-sm">Edit</a>
        @if($lead->client_id)
            <a href="{{ route('admin.clients.show', $lead->client_id) }}" class="btn btn-outline-success btn-sm">View Customer</a>
            @if(!auth()->user()->hasRole('Admin'))
            <a href="{{ route('admin.sales.create', ['lead_id' => $lead->id, 'client_id' => $lead->client_id]) }}" class="btn btn-primary btn-sm">Create Sale</a>
            @endif
        @else
            <form action="{{ route('admin.leads.convert', $lead) }}" method="POST" class="d-inline">
                @csrf
                <button class="btn btn-success btn-sm" type="submit">
                    {{ auth()->user()->hasRole('Admin') ? 'Convert to Customer' : 'Convert & Create Sale' }}
                </button>
            </form>
        @endif
        <a href="{{ route('admin.leads.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if($lead->isFollowUpOverdue())
        <div class="alert alert-danger py-2">Follow-up is overdue ({{ $lead->follow_up_at->format('d M Y') }}). Log activity or set a new date.</div>
    @elseif($lead->follow_up_at)
        <div class="alert alert-info py-2">Next follow-up: <strong>{{ $lead->follow_up_at->format('d M Y') }}</strong></div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <small class="text-muted d-block">Name</small>
                    <strong>{{ $lead->name }}</strong>
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Status</small>
                    @php
                        $colors = [
                            'new_lead' => 'primary',
                            'contacted' => 'info',
                            'follow_up' => 'info',
                            'proposal' => 'warning',
                            'customer' => 'success',
                            'lost' => 'danger',
                        ];
                    @endphp
                    <span class="badge bg-{{ $colors[$lead->status] ?? 'secondary' }}">{{ $lead->statusLabel() }}</span>
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Email</small>
                    {{ $lead->email ?: '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Phone</small>
                    {{ $lead->phone ?: '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Source</small>
                    {{ $lead->source ? ucwords(str_replace('_', ' ', $lead->source)) : '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Assigned To</small>
                    {{ $lead->assignedUser?->name ?? '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Team</small>
                    {{ $lead->team?->name ?? '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Brand</small>
                    {{ $lead->brand?->name ?? '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Lead Value</small>
                    ${{ number_format((float) $lead->lead_value, 2) }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Last Contact</small>
                    {{ $lead->last_contact_at?->format('d M Y') ?? '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Next Follow-up</small>
                    {{ $lead->follow_up_at?->format('d M Y') ?? '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Created</small>
                    {{ $lead->created_at->format('d M Y, h:i A') }}
                </div>
                <div class="col-12">
                    <small class="text-muted d-block">Notes</small>
                    {{ $lead->notes ?: '-' }}
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white"><strong>Activity / Follow-up Log</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.leads.activities.store', $lead) }}" class="row g-2 mb-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Note</label>
                    <textarea name="body" class="form-control" rows="2" required placeholder="Call done / WhatsApp sent / next step..."></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="note">Note</option>
                        <option value="contact">Contact made</option>
                        <option value="follow_up">Follow-up update</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Set next follow-up</label>
                    <input type="date" name="follow_up_at" class="form-control" value="{{ $lead->follow_up_at?->toDateString() }}">
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-primary btn-sm">Add Activity</button>
                </div>
            </form>

            @forelse($lead->activities as $activity)
                <div class="border-bottom py-2">
                    <div class="d-flex justify-content-between gap-2">
                        <strong class="small">{{ $activity->user?->name ?? 'System' }}</strong>
                        <span class="text-muted small">{{ $activity->created_at->format('d M Y, h:i A') }}</span>
                    </div>
                    <div class="small text-muted text-uppercase">{{ str_replace('_', ' ', $activity->type) }}</div>
                    <div>{{ $activity->body }}</div>
                </div>
            @empty
                <div class="text-muted small">No activity logged yet.</div>
            @endforelse
        </div>
    </div>
@endsection
