@extends('admin.layout')
@php $editing = isset($invoice); @endphp
@section('title', $editing ? 'Edit Invoice' : 'Create Invoice')
@section('page-title', $editing ? 'Edit Invoice '.$invoice->invoice_number : 'Create New Invoice')
@section('page-icon', 'receipt')
@section('content')
<form method="POST" action="{{ $editing ? route('admin.invoices.update', $invoice) : route('admin.invoices.store-standalone') }}">
    @csrf
    @if($editing)
        @method('PUT')
    @endif
    <div class="card mb-3"><div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Client <span class="text-danger">*</span></label>
                <select name="client_id" id="invoice_client_id" class="form-select" required>
                    <option value="">Select client</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}"
                            data-brand-id="{{ $client->brand_id }}"
                            {{ (string) old('client_id', $invoice->client_id ?? '') === (string) $client->id ? 'selected' : '' }}>
                            {{ $client->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Brand <span class="text-danger">*</span></label>
                <select name="brand_id" id="invoice_brand_id" class="form-select js-select2" required data-placeholder="Select brand">
                    <option value="">-- Select Brand --</option>
                    @foreach(($brands ?? []) as $brand)
                        <option value="{{ $brand->id }}" {{ (string) old('brand_id', $invoice->brand_id ?? '') === (string) $brand->id ? 'selected' : '' }}>
                            {{ $brand->name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Defaults to the client’s brand; you can change it for this invoice.</div>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                <input type="date" name="issued_at" class="form-control" required value="{{ old('issued_at', isset($invoice) ? $invoice->issued_at?->toDateString() : now()->toDateString()) }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" class="form-control" value="{{ old('due_date', isset($invoice) ? $invoice->due_date?->toDateString() : now()->addDay()->toDateString()) }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Currency</label>
                <select name="currency" class="form-select">
                    @foreach(['USD', 'GBP', 'EUR', 'PKR'] as $currency)
                        <option value="{{ $currency }}" {{ old('currency', $invoice->currency ?? 'USD') === $currency ? 'selected' : '' }}>{{ $currency }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-9 mb-3">
                <label class="form-label">Admin Note</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $invoice->notes ?? '') }}</textarea>
            </div>
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <div class="table-responsive">
            <table class="table" id="invoice-items">
                <thead>
                <tr>
                    <th>Item</th><th>Description</th><th style="width:90px">Qty</th><th style="width:120px">Rate</th><th style="width:100px">Tax %</th><th></th>
                </tr>
                </thead>
                <tbody>
                @php
                    $oldItems = old('items');
                    $seedItems = $oldItems ?? (isset($invoice) ? $invoice->items->map(fn ($item) => [
                        'description' => $item->description,
                        'long_description' => $item->long_description,
                        'qty' => $item->qty,
                        'rate' => $item->rate,
                        'tax_rate' => $item->tax_rate,
                    ])->all() : []);
                    if ($seedItems === []) {
                        $seedItems = [[], [], []];
                    }
                @endphp
                @foreach($seedItems as $i => $row)
                    <tr>
                        <td><input name="items[{{ $i }}][description]" class="form-control" value="{{ $row['description'] ?? '' }}"></td>
                        <td><input name="items[{{ $i }}][long_description]" class="form-control" value="{{ $row['long_description'] ?? '' }}"></td>
                        <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][qty]" class="form-control" value="{{ $row['qty'] ?? 1 }}"></td>
                        <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][rate]" class="form-control" value="{{ $row['rate'] ?? 0 }}"></td>
                        <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][tax_rate]" class="form-control" value="{{ $row['tax_rate'] ?? 0 }}"></td>
                        <td></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="add-item">Add Item</button>
        <div class="row justify-content-end mt-3">
            <div class="col-md-4">
                <label class="form-label">Discount</label>
                <input type="number" step="0.01" min="0" name="discount" class="form-control mb-2" value="{{ old('discount', $invoice->discount ?? 0) }}">
                <label class="form-label">Adjustment</label>
                <input type="number" step="0.01" name="adjustment" class="form-control" value="{{ old('adjustment', $invoice->adjustment ?? 0) }}">
            </div>
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Client Note</label>
                <textarea name="client_note" class="form-control" rows="3">{{ old('client_note', $invoice->client_note ?? '') }}</textarea>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Terms & Conditions</label>
                <textarea name="terms" class="form-control" rows="3">{{ old('terms', $invoice->terms ?? '') }}</textarea>
            </div>
        </div>
        <div class="text-end">
            <a href="{{ $editing ? route('admin.invoices.show', $invoice) : route('admin.invoices.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-outline-secondary" name="save_as_draft" value="1">Save as Draft</button>
            <button class="btn btn-primary">{{ $editing ? 'Save & Issue' : 'Save' }}</button>
        </div>
    </div></div>
</form>
@endsection
@section('scripts')
<script>
document.getElementById('add-item')?.addEventListener('click', function () {
    const body = document.querySelector('#invoice-items tbody');
    const index = body.querySelectorAll('tr').length;
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input name="items[${index}][description]" class="form-control"></td>
        <td><input name="items[${index}][long_description]" class="form-control"></td>
        <td><input type="number" step="0.01" min="0" name="items[${index}][qty]" class="form-control" value="1"></td>
        <td><input type="number" step="0.01" min="0" name="items[${index}][rate]" class="form-control" value="0"></td>
        <td><input type="number" step="0.01" min="0" name="items[${index}][tax_rate]" class="form-control" value="0"></td>
        <td></td>`;
    body.appendChild(row);
});

(function () {
    const clientSelect = document.getElementById('invoice_client_id');
    const brandSelect = document.getElementById('invoice_brand_id');
    if (!clientSelect || !brandSelect) return;

    const syncBrandFromClient = () => {
        const option = clientSelect.options[clientSelect.selectedIndex];
        const brandId = option?.getAttribute('data-brand-id') || '';
        if (brandId && brandSelect.querySelector('option[value="' + brandId + '"]')) {
            brandSelect.value = brandId;
            if (window.jQuery) window.jQuery(brandSelect).trigger('change.select2'); // refresh the Select2 box
        }
    };

    clientSelect.addEventListener('change', syncBrandFromClient);
    @if(! $editing && ! old('brand_id'))
    if (!brandSelect.value) {
        syncBrandFromClient();
    }
    @endif
})();
</script>
@endsection
