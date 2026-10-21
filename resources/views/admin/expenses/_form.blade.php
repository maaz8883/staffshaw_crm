@csrf
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Category <span class="text-danger">*</span></label>
        <input type="text" name="category" class="form-control" list="expense-categories" required value="{{ old('category', $expense->category ?? '') }}">
        <datalist id="expense-categories">
            @foreach(\App\Models\Expense::CATEGORIES as $category)
                <option value="{{ $category }}">
            @endforeach
        </datalist>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Amount (Rs) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="amount" class="form-control" required value="{{ old('amount', $expense->amount ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Date <span class="text-danger">*</span></label>
        <input type="date" name="expense_date" class="form-control" required value="{{ old('expense_date', isset($expense) ? $expense->expense_date?->format('Y-m-d') : now()->toDateString()) }}">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Client</label>
        <select name="client_id" class="form-select js-select2">
            <option value="">None</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" {{ (string) old('client_id', $expense->client_id ?? '') === (string) $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Brand</label>
        <select name="brand_id" class="form-select js-select2">
            <option value="">No Brand</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->id }}" {{ (string) old('brand_id', $expense->brand_id ?? '') === (string) $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Note</label>
        <textarea name="note" class="form-control" rows="3">{{ old('note', $expense->note ?? '') }}</textarea>
    </div>
    <div class="col-12 mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_billable" value="1" id="is_billable" {{ old('is_billable', $expense->is_billable ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_billable">Billable</label>
        </div>
    </div>
</div>
