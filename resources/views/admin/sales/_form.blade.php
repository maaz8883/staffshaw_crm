@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
        <input type="text" id="title" name="title" class="form-control"
            value="{{ old('title', $sale->title ?? '') }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="client_name" class="form-label">Client Name <span class="text-danger">*</span></label>
        <input type="text" id="client_name" name="client_name" class="form-control"
            value="{{ old('client_name', $sale->client_name ?? '') }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="amount" class="form-label">Amount ($) <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" id="amount" name="amount" class="form-control"
                step="0.01" min="0"
                value="{{ old('amount', $sale->amount ?? '') }}" required>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <label for="sale_date" class="form-label">Sale Date <span class="text-danger">*</span></label>
        <input type="date" id="sale_date" name="sale_date" class="form-control"
            value="{{ old('sale_date', isset($sale) ? $sale->sale_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        @if(!empty($sale) && $sale->is_refunded)
            <div>
                <span class="badge bg-dark">{{ $sale->statusLabel() }}</span>
                <p class="text-muted small mb-0 mt-1">Refund is managed from the sales list; status stays Refunded until reverted.</p>
            </div>
        @else
        <select id="status" name="status" class="form-select" required>
            @foreach(\App\Models\Sale::STATUSES as $s)
                <option value="{{ $s }}" {{ old('status', $sale->status ?? 'completed') === $s ? 'selected' : '' }}>
                    {{ ucfirst($s) }}
                </option>
            @endforeach
        </select>
        @endif
    </div>

    <div class="col-md-6 mb-3">
        <label for="sale_type" class="form-label">Sale type <span class="text-danger">*</span></label>
        <select id="sale_type" name="sale_type" class="form-select" required>
            @php $st = old('sale_type', isset($sale) ? $sale->sale_type : \App\Models\Sale::TYPE_FRONT); @endphp
            <option value="{{ \App\Models\Sale::TYPE_FRONT }}" {{ $st === \App\Models\Sale::TYPE_FRONT ? 'selected' : '' }}>
                Front
            </option>
            <option value="{{ \App\Models\Sale::TYPE_UPSELL }}" {{ $st === \App\Models\Sale::TYPE_UPSELL ? 'selected' : '' }}>
                Upsell
            </option>
        </select>
        <div class="form-text">Refunds are marked by admin or team lead from the sales list.</div>
    </div>

    <div class="col-12 mb-3">
        <label for="notes" class="form-label">Notes</label>
        <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $sale->notes ?? '') }}</textarea>
    </div>
</div>
