@extends('admin.layout')

@section('title', 'Client Details')
@section('page-title', 'Client Details')
@section('page-icon', 'person-lines-fill')

@section('content')

<div class="mb-3">
    @if($canSeeDetails && ! auth()->user()->hasRole('Finance'))<a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-outline-warning btn-sm">Edit</a>@endif
    <a href="{{ route('admin.clients.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-1">{{ $client->name }}</h5>
        <div class="text-muted mb-2">
            {{ $client->company_name ?: 'No company' }}
            · Team: {{ $client->team?->name ?? '-' }}
            · Brand: {{ $client->brand?->name ?: '-' }}
            · {{ $client->is_active ? 'Active' : 'Inactive' }}
        </div>
        @unless($canSeeDetails)
            <div class="alert alert-light border small py-2 mb-3">
                <i class="bi bi-lock"></i> This client is handled by another agent, so contact details are hidden. You can still see their Trello projects below.
            </div>
        @endunless
        <div class="row g-3">
            @if($canSeeDetails)
            <div class="col-md-6">
                <small class="text-muted d-block">Email</small>
                {{ $client->email ?: '-' }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Phone</small>
                {{ $client->phone ?: '-' }}
            </div>
            @endif
            <div class="col-md-6">
                <small class="text-muted d-block">Added By</small>
                {{ $client->createdBy?->name ?? '-' }}
            </div>
            @if($canSeeDetails)
            <div class="col-12">
                <small class="text-muted d-block">Notes</small>
                {{ $client->notes ?: '-' }}
            </div>
            @endif
        </div>
    </div>
</div>

@if($canSeeDetails && $client->contacts->isNotEmpty())
<div class="card mb-3">
    <div class="card-header">Contacts</div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Position</th></tr></thead>
            <tbody>
            @foreach($client->contacts as $contact)
                <tr>
                    <td>{{ $contact->fullName() }} @if($contact->is_primary)<span class="badge bg-light text-dark">Primary</span>@endif</td>
                    <td>{{ $contact->email ?: '-' }}</td>
                    <td>{{ $contact->phone ?: '-' }}</td>
                    <td>{{ $contact->position ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Trello projects: every card that carries the client's name (loaded after the page) --}}
<div class="card border-0 shadow-sm mb-3" id="client-trello">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-kanban me-1"></i> Trello projects <span class="text-muted fw-normal small" id="ct-count"></span></span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="ct-refresh"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Project</th><th>Board</th><th>List</th><th class="text-end">Due</th></tr></thead>
                <tbody id="ct-body"><tr><td colspan="4" class="text-muted small">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>
</div>
<script>
(function () {
    const url = @json(route('admin.clients.trello', $client));
    const body = document.getElementById('ct-body');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    const dueText = (iso) => new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

    async function load(refresh = false) {
        body.innerHTML = '<tr><td colspan="4" class="text-muted small">Loading…</td></tr>';
        try {
            const res = await fetch(url + (refresh ? '?refresh=1' : ''), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) throw new Error(data.message || 'Could not load Trello projects');
            const cards = data.cards || [];
            document.getElementById('ct-count').textContent = cards.length ? `(${cards.length})` : '';
            if (!cards.length) {
                body.innerHTML = '<tr><td colspan="4" class="text-muted small">No Trello card carries this client\'s name.</td></tr>';
                return;
            }
            body.innerHTML = cards.map((c) => {
                const due = c.done ? '<span class="badge bg-success-subtle text-success-emphasis">Completed</span>'
                    : (c.due ? `<span class="${c.late ? 'text-danger fw-semibold' : ''}">${esc(dueText(c.due))}${c.late ? ' · late' : ''}</span>` : '<span class="text-muted">—</span>');
                const name = c.url ? `<a href="${esc(c.url)}" class="text-decoration-none fw-semibold">${esc(c.name)}</a>` : `<span class="fw-semibold">${esc(c.name)}</span>`;
                return `<tr><td>${name}</td><td class="small">${esc(c.board)}</td><td class="small">${esc(c.list)}</td><td class="text-end small">${due}</td></tr>`;
            }).join('');
        } catch (e) {
            body.innerHTML = `<tr><td colspan="4" class="text-muted small">${esc(e.message)}</td></tr>`;
        }
    }

    document.getElementById('ct-refresh').addEventListener('click', () => load(true));
    load();
})();
</script>

{{-- Summary cards --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Total Sales</div>
                <div class="fs-4 fw-bold text-primary">{{ $sales->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Total Sale Amount</div>
                <div class="fs-4 fw-bold text-success">${{ number_format($totalSaleAmount, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Remaining</div>
                <div class="fs-4 fw-bold {{ $totalRemaining > 0 ? 'text-warning' : 'text-success' }}">${{ number_format($totalRemaining, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Invoices</div>
                <div class="fs-4 fw-bold text-info">{{ $invoices->count() }} <span class="fs-6 text-muted">(${{ number_format($totalInvoiced, 2) }})</span></div>
            </div>
        </div>
    </div>
</div>

{{-- Sales --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-cash-stack text-primary"></i> Sales for this Client
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Agent</th>
                        <th>Team</th>
                        <th>Status</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                    @php $sc = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger', \App\Models\Sale::STATUS_REFUNDED=>'dark']; @endphp
                    <tr>
                        <td><a href="{{ route('admin.sales.show', $sale) }}" class="text-decoration-none">{{ $sale->title }}</a></td>
                        <td class="text-muted small">{{ $sale->user?->name ?? '-' }}</td>
                        <td class="text-muted small">{{ $sale->team?->name ?? '-' }}</td>
                        <td><span class="badge bg-{{ $sc[$sale->status] ?? 'secondary' }}">{{ $sale->statusLabel() }}</span></td>
                        <td class="text-end fw-semibold">${{ number_format($sale->amount, 2) }}</td>
                        <td class="text-end">${{ number_format($sale->received_amount, 2) }}</td>
                        <td class="text-end {{ $sale->remainingAmount() > 0 ? 'text-warning' : 'text-success' }}">${{ number_format($sale->remainingAmount(), 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-3">No sales recorded for this client yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Invoices --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-receipt text-primary"></i> Invoices for this Client
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Sale</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->invoice_number }}</td>
                        <td>
                            @if($invoice->sale_id)
                                <a href="{{ route('admin.sales.show', $invoice->sale_id) }}" class="text-decoration-none">#{{ $invoice->sale_id }} — {{ $invoice->title }}</a>
                            @else
                                <span class="text-muted">{{ $invoice->title ?: 'Standalone' }}</span>
                            @endif
                        </td>
                        <td>{{ $invoice->issued_at->format('d M Y') }}</td>
                        <td class="text-end fw-semibold">${{ number_format($invoice->amount, 2) }}</td>
                        <td class="text-end"><a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-info">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted text-center py-3">No invoices generated for this client yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
