@php
    $forPdf = $forPdf ?? false;
    $isSaleReceipt = (bool) $invoice->sale_id;
    $hasItems = $invoice->items->isNotEmpty();
    // Sale payment receipts: description + amount only (no qty/rate/tax).
    $showLineDetails = $hasItems && ! $isSaleReceipt;
    $showTax = $showLineDetails && $invoice->items->contains(fn ($item) => (float) $item->tax_rate > 0);
    $itemColCount = $showLineDetails ? (2 + ($showTax ? 1 : 0)) : 0; // qty + rate [+ tax]
    $labelColspan = $showLineDetails ? (1 + $itemColCount) : 1;
@endphp
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        @if($invoice->brand?->image)
            @php
                $brandImagePath = public_path('storage/' . $invoice->brand->image);
                $brandImageSrc = $forPdf && is_file($brandImagePath)
                    ? $brandImagePath
                    : asset('storage/' . $invoice->brand->image);
            @endphp
            <img src="{{ $brandImageSrc }}" alt="{{ $invoice->brand_name }}" style="max-height:60px; max-width:200px;" class="mb-2">
        @endif
        <h4 class="mb-0">{{ $invoice->brand_name ?? 'Invoice' }}</h4>
        @if($invoice->company_name)
            <div class="text-muted small">{{ $invoice->company_name }}</div>
        @endif
    </div>
    <div class="text-md-end">
        <h3 class="mb-1">{{ $isSaleReceipt ? 'PAYMENT RECEIPT' : 'INVOICE' }}</h3>
        <div><strong>{{ $invoice->invoice_number }}</strong></div>
        <div class="text-muted">Date: {{ $invoice->issued_at->format('d M Y') }}</div>
        @if($invoice->due_date && ! $isSaleReceipt)
            <div class="text-muted">Due: {{ $invoice->due_date->format('d M Y') }}</div>
        @endif
        @if(! $forPdf)
            <div class="mt-2 d-print-none"><span class="badge bg-{{ $invoice->statusColor() }}">{{ $invoice->statusLabel() }}</span></div>
        @endif
        @if($invoice->isVoid())
            <span class="badge bg-secondary mt-2">VOID</span>
        @endif
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <h6 class="text-muted text-uppercase small mb-2">Bill To</h6>
        <div class="fw-semibold">{{ $invoice->client_name }}</div>
        @if($invoice->client_email)
            <div>{{ $invoice->client_email }}</div>
        @endif
        @if($invoice->client_phone)
            <div>{{ $invoice->client_phone }}</div>
        @endif
    </div>
    <div class="col-md-6 text-md-end">
        @if($isSaleReceipt && ! $forPdf)
            <div class="d-print-none">
                <h6 class="text-muted text-uppercase small mb-2">Sale Reference</h6>
                <div>Sale #{{ $invoice->sale_id }}</div>
                @if($invoice->agent_name)
                    <div class="small text-muted">Agent: {{ $invoice->agent_name }}</div>
                @endif
                @if($invoice->team_name)
                    <div class="small text-muted">Team: {{ $invoice->team_name }}</div>
                @endif
            </div>
        @elseif(! $isSaleReceipt)
            <h6 class="text-muted text-uppercase small mb-2">Status</h6>
            <div>{{ $invoice->statusLabel() }}</div>
            <div class="small text-muted">Open ${{ number_format($invoice->amountDue(), 2) }}</div>
        @endif
    </div>
</div>

<table class="table table-bordered mb-3">
    <thead class="table-light">
        <tr>
            <th>Description</th>
            @if($showLineDetails)
                <th class="text-end">Qty</th>
                <th class="text-end">Rate</th>
                @if($showTax)
                    <th class="text-end">Tax</th>
                @endif
            @endif
            <th class="text-end" style="width:140px">Amount</th>
        </tr>
    </thead>
    <tbody>
        @if($hasItems)
            @foreach($invoice->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->description }}</strong>
                        @if($item->long_description)
                            <div class="text-muted small">{{ $item->long_description }}</div>
                        @endif
                    </td>
                    @if($showLineDetails)
                        <td class="text-end">{{ rtrim(rtrim(number_format($item->qty, 2), '0'), '.') }}</td>
                        <td class="text-end">${{ number_format($item->rate, 2) }}</td>
                        @if($showTax)
                            <td class="text-end">{{ number_format($item->tax_rate, 2) }}%</td>
                        @endif
                    @endif
                    <td class="text-end">${{ number_format($item->amount, 2) }}</td>
                </tr>
            @endforeach
        @else
        <tr>
            <td>
                <strong>{{ $invoice->title }}</strong>
                @if($invoice->notes)
                    <div class="text-muted small mt-1">{!! nl2br(e($invoice->notes)) !!}</div>
                @endif
            </td>
            <td class="text-end">${{ number_format($invoice->amount, 2) }}</td>
        </tr>
        @endif
    </tbody>
    <tfoot>
        @if((float) $invoice->discount > 0)
        <tr>
            <td class="text-end" colspan="{{ $labelColspan }}">Discount</td>
            <td class="text-end">-${{ number_format($invoice->discount, 2) }}</td>
        </tr>
        @endif
        @if((float) $invoice->adjustment != 0)
        <tr>
            <td class="text-end" colspan="{{ $labelColspan }}">Adjustment</td>
            <td class="text-end">${{ number_format($invoice->adjustment, 2) }}</td>
        </tr>
        @endif
        <tr>
            <th class="text-end" colspan="{{ $labelColspan }}">{{ $isSaleReceipt ? 'Amount Received' : 'Invoice Amount' }}</th>
            <th class="text-end">${{ number_format($invoice->amount, 2) }} {{ $invoice->currency }}</th>
        </tr>
        @if(! $isSaleReceipt)
        <tr>
            <td class="text-end" colspan="{{ $labelColspan }}">Amount Paid</td>
            <td class="text-end">${{ number_format($invoice->amountPaid(), 2) }}</td>
        </tr>
        <tr>
            <th class="text-end" colspan="{{ $labelColspan }}">Balance Due</th>
            <th class="text-end">${{ number_format($invoice->amountDue(), 2) }}</th>
        </tr>
        @endif
    </tfoot>
</table>

@if(! $isSaleReceipt && $invoice->payments->isNotEmpty())
    <h6 class="text-muted text-uppercase small mb-2 mt-3">Payment History</h6>
    <table class="table table-bordered table-sm mb-3">
        <thead class="table-light">
            <tr>
                <th>Date</th>
                <th>Mode</th>
                <th>Note</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->payments->sortBy('paid_at') as $payment)
                <tr>
                    <td>{{ $payment->paid_at?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $payment->payment_mode ?: '—' }}</td>
                    <td class="text-muted">{{ $payment->note ?: '—' }}</td>
                    <td class="text-end">${{ number_format($payment->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($invoice->client_note)
    <p class="small"><strong>Client note:</strong> {{ $invoice->client_note }}</p>
@endif
@if($invoice->terms)
    <p class="small"><strong>Terms:</strong> {{ $invoice->terms }}</p>
@endif

@if(! $forPdf)
<p class="text-muted small mb-0 d-print-none">
    Generated by {{ $invoice->creator?->name ?? 'System' }}
    on {{ $invoice->created_at->format('d M Y, h:i A') }}.
</p>
@endif
