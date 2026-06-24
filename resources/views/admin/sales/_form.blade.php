@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="title" class="form-label">Project Title <span class="text-danger">*</span></label>
        <input type="text" id="title" name="title" class="form-control"
            value="{{ old('title', $sale->title ?? '') }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="client_name" class="form-label">Client Name <span class="text-danger">*</span></label>
        <input type="text" id="client_name" name="client_name" class="form-control"
            value="{{ old('client_name', $sale->client_name ?? '') }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="client_email" class="form-label">Client Email</label>
        <input type="email" id="client_email" name="client_email" class="form-control"
            value="{{ old('client_email', $sale->client_email ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
        <label for="client_phone" class="form-label">Client Phone</label>
        <input type="text" id="client_phone" name="client_phone" class="form-control"
            value="{{ old('client_phone', $sale->client_phone ?? '') }}">
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
        <label for="notes" class="form-label">Description</label>
        <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $sale->notes ?? '') }}</textarea>
    </div>
</div>
