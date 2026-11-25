@csrf
@php
    $pf = $leadPrefill ?? [];
    $defaultSaleType = $suggestedSaleType ?? \App\Models\Sale::TYPE_FRONT;
@endphp
@if(!empty($pf['lead_id']))
    <input type="hidden" name="lead_id" value="{{ $pf['lead_id'] }}">
    <div class="alert alert-info py-2">Creating sale from lead #{{ $pf['lead_id'] }}.</div>
@endif

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="title" class="form-label">Project Title <span class="text-danger">*</span></label>
        <input type="text" id="title" name="title" class="form-control"
            value="{{ old('title', $sale->title ?? ($pf['title'] ?? '')) }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="brand_id" class="form-label">Brand <span class="text-danger">*</span></label>
        <select id="brand_id" name="brand_id" class="form-select js-select2" required data-placeholder="Select brand">
            <option value="">-- Select Brand --</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->id }}"
                    {{ old('brand_id', $sale->brand_id ?? ($pf['brand_id'] ?? '')) == $brand->id ? 'selected' : '' }}>
                    {{ $brand->name }}
                </option>
            @endforeach
        </select>
    </div>

    @if(($isProjectManager ?? false) && !isset($sale))
    <div class="col-md-6 mb-3">
        <label for="team_id" class="form-label">Team <span class="text-danger">*</span></label>
        <select id="team_id" name="team_id" class="form-select js-select2" required>
            <option value="">-- Select a joined team --</option>
            @foreach($joinedTeams as $team)
                <option value="{{ $team->id }}" {{ old('team_id') == $team->id ? 'selected' : '' }}>
                    {{ $team->name }}
                </option>
            @endforeach
        </select>
        <div class="form-text">You can only create sales for teams you have joined and been approved for.</div>
    </div>
    @endif

    <div class="col-md-6 mb-3">
        <label for="client_id" class="form-label">Existing Client</label>
        <select id="client_id" name="client_id" class="form-select js-select2">
            <option value="">-- New client (fill in below) --</option>
            @foreach($clients ?? [] as $c)
                <option value="{{ $c->id }}"
                    data-name="{{ $c->name }}" data-email="{{ $c->email }}" data-phone="{{ $c->phone }}"
                    {{ old('client_id', $sale->client_id ?? ($pf['client_id'] ?? '')) == $c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Select a saved client to auto-fill their details, or leave blank and type a new client below.</div>
    </div>

    <div class="col-md-6 mb-3">
        <label for="client_name" class="form-label">Client Name <span class="text-danger">*</span></label>
        <input type="text" id="client_name" name="client_name" class="form-control"
            value="{{ old('client_name', $sale->client_name ?? ($pf['client_name'] ?? '')) }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="client_email" class="form-label">Client Email</label>
        <input type="email" id="client_email" name="client_email" class="form-control"
            value="{{ old('client_email', $sale->client_email ?? ($pf['client_email'] ?? '')) }}">
    </div>

    <div class="col-md-6 mb-3">
        <label for="client_phone" class="form-label">Client Phone</label>
        <input type="text" id="client_phone" name="client_phone" class="form-control"
            value="{{ old('client_phone', $sale->client_phone ?? ($pf['client_phone'] ?? '')) }}">
    </div>

    <div class="col-md-6 mb-3">
        <label for="amount" class="form-label">Amount ($) <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" id="amount" name="amount" class="form-control"
                step="0.01" min="0"
                value="{{ old('amount', $sale->amount ?? ($pf['amount'] ?? '')) }}" required>
        </div>
        <div class="form-text">
            Total sale amount.
            <span id="remaining_display" class="fw-semibold {{ (isset($sale) && $sale->remainingAmount() > 0) ? 'text-warning' : 'text-success' }}">
                Remaining: ${{ number_format(isset($sale) ? $sale->remainingAmount() : 0, 2) }}
            </span>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <label for="received_amount" class="form-label">Received Amount ($)</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" id="received_amount" name="received_amount" class="form-control"
                step="0.01" min="0"
                value="{{ old('received_amount', $sale->received_amount ?? 0) }}">
        </div>
        <div class="form-text">Initial amount received (optional). After approval, add more via <strong>Record Payment Receipt</strong> on the sale.</div>
    </div>

    <div class="col-md-6 mb-3">
        <label for="sale_date" class="form-label">Sale Date <span class="text-danger">*</span></label>
        <input type="date" id="sale_date" name="sale_date" class="form-control"
            value="{{ old('sale_date', isset($sale) ? $sale->sale_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>

    <div class="col-md-6 mb-3">
        <label for="sale_type" class="form-label">Sale type <span class="text-danger">*</span></label>
        <select id="sale_type" name="sale_type" class="form-select" required>
            @php $st = old('sale_type', isset($sale) ? $sale->sale_type : $defaultSaleType); @endphp
            <option value="{{ \App\Models\Sale::TYPE_FRONT }}" {{ $st === \App\Models\Sale::TYPE_FRONT ? 'selected' : '' }}>
                Front
            </option>
            <option value="{{ \App\Models\Sale::TYPE_UPSELL }}" {{ $st === \App\Models\Sale::TYPE_UPSELL ? 'selected' : '' }}>
                Upsell
            </option>
        </select>
        <div class="form-text">Defaults from your Front/Upsell seat when set. Refunds are marked by admin or team lead.</div>
    </div>

    @if(isset($sale))
    <div class="col-md-6 mb-3">
        <label for="user_id" class="form-label">Sales Agent</label>
        <select id="user_id" name="user_id" class="form-select js-select2" data-placeholder="Unassigned">
            <option value="">— Unassigned —</option>
            @foreach($staffUsers ?? [] as $u)
                <option value="{{ $u->id }}"
                    {{ (string) old('user_id', $sale->user_id ?? '') === (string) $u->id ? 'selected' : '' }}>
                    {{ $u->name }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Imported sales with no matching agent can be assigned here.</div>
    </div>
    @endif

    <div class="col-md-6 mb-3">
        <label for="merchant" class="form-label">Merchant</label>
        <input type="text" id="merchant" name="merchant" class="form-control" list="merchant-suggestions"
            maxlength="120"
            value="{{ old('merchant', $sale->merchant ?? '') }}"
            placeholder="e.g. Stripe, PayPal, Bank">
        <datalist id="merchant-suggestions">
            <option value="Stripe"></option>
            <option value="PayPal"></option>
            <option value="Square"></option>
            <option value="Authorize.net"></option>
            <option value="Bank Transfer"></option>
            <option value="Cash"></option>
        </datalist>
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label d-block">PPC</label>
        @php $ppc = (string) old('is_ppc', isset($sale) ? ($sale->is_ppc ? '1' : '0') : '0'); @endphp
        <div class="form-check form-switch mt-1">
            <input class="form-check-input" type="checkbox" role="switch" id="is_ppc" name="is_ppc" value="1"
                {{ $ppc === '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="is_ppc">This sale came from PPC</label>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <label for="account_manager_id" class="form-label">Account Manager</label>
        <select id="account_manager_id" name="account_manager_id" class="form-select js-select2" data-placeholder="Optional">
            <option value="">— None —</option>
            @foreach($staffUsers ?? [] as $u)
                <option value="{{ $u->id }}"
                    {{ (string) old('account_manager_id', $sale->account_manager_id ?? '') === (string) $u->id ? 'selected' : '' }}>
                    {{ $u->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 mb-3">
        <label for="project_manager_id" class="form-label">Project Manager</label>
        <select id="project_manager_id" name="project_manager_id" class="form-select js-select2" data-placeholder="Optional">
            <option value="">— None —</option>
            @foreach($staffUsers ?? [] as $u)
                <option value="{{ $u->id }}"
                    {{ (string) old('project_manager_id', $sale->project_manager_id ?? '') === (string) $u->id ? 'selected' : '' }}>
                    {{ $u->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-12 mb-3">
        <label for="notes" class="form-label">Description</label>
        <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $sale->notes ?? '') }}</textarea>
    </div>
</div>

<script>
(function () {
    const clientSelect = document.getElementById('client_id');
    const clientNameInput = document.getElementById('client_name');
    const clientEmailInput = document.getElementById('client_email');
    const clientPhoneInput = document.getElementById('client_phone');

    if (clientSelect) {
        clientSelect.addEventListener('change', function () {
            const opt = clientSelect.options[clientSelect.selectedIndex];
            if (!opt || !opt.value) return;

            clientNameInput.value = opt.dataset.name || '';
            clientEmailInput.value = opt.dataset.email || '';
            clientPhoneInput.value = opt.dataset.phone || '';
        });
    }
})();

(function () {
    const amountInput = document.getElementById('amount');
    const receivedInput = document.getElementById('received_amount');
    const remainingDisplay = document.getElementById('remaining_display');

    if (amountInput && receivedInput && remainingDisplay) {
        function updateRemaining() {
            const total = parseFloat(amountInput.value) || 0;
            const received = parseFloat(receivedInput.value) || 0;
            const remaining = Math.max(0, total - received);

            remainingDisplay.textContent = 'Remaining: $' + remaining.toFixed(2);
            remainingDisplay.classList.toggle('text-warning', remaining > 0);
            remainingDisplay.classList.toggle('text-success', remaining <= 0);
        }

        amountInput.addEventListener('input', updateRemaining);
        receivedInput.addEventListener('input', updateRemaining);
        updateRemaining();
    }
})();
</script>
