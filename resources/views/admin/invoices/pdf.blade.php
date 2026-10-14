@php
    $isSaleReceipt = (bool) $invoice->sale_id;
    $hasItems = $invoice->items->isNotEmpty();
    $showLineDetails = $hasItems && ! $isSaleReceipt;
    $showTax = $showLineDetails && $invoice->items->contains(fn ($item) => (float) $item->tax_rate > 0);
    $labelColspan = $showLineDetails ? (1 + 2 + ($showTax ? 1 : 0)) : 1;

    $brandImgSrc = null;
    if ($invoice->brand?->image) {
        $brandImagePath = public_path('storage/' . $invoice->brand->image);
        if (is_file($brandImagePath)) {
            $mime = @mime_content_type($brandImagePath) ?: 'image/png';
            $brandImgSrc = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($brandImagePath));
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #212529;
            margin: 0;
            padding: 0;
            width: 100%;
        }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; padding: 0 0 18px; border: none; }
        .brand-name { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
        .company { color: #6c757d; font-size: 11px; }
        .doc-title { font-size: 20px; font-weight: 700; margin: 0 0 6px; text-align: right; }
        .meta { text-align: right; color: #495057; line-height: 1.5; }
        .section { margin: 0 0 18px; }
        .label {
            color: #6c757d;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0 0 6px;
            font-weight: 700;
        }
        .bill td { vertical-align: top; padding: 0; border: none; }
        .items th, .items td {
            border: 1px solid #dee2e6;
            padding: 8px 10px;
        }
        .items thead th {
            background: #f1f3f5;
            text-align: left;
            font-size: 11px;
        }
        .items .num { text-align: right; white-space: nowrap; }
        .items tfoot th, .items tfoot td {
            background: #fff;
            border: 1px solid #dee2e6;
            padding: 8px 10px;
        }
        .muted { color: #6c757d; }
        .small { font-size: 11px; }
        img.brand-logo { max-height: 52px; max-width: 180px; display: block; margin-bottom: 6px; }
        .void {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 8px;
            background: #6c757d;
            color: #fff;
            font-size: 10px;
        }
        .note { margin-top: 14px; font-size: 11px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="55%">
                @if($brandImgSrc)
                    <img class="brand-logo" src="{{ $brandImgSrc }}" alt="">
                @endif
                <div class="brand-name">{{ $invoice->brand_name ?? 'Invoice' }}</div>
                @if($invoice->company_name)
                    <div class="company">{{ $invoice->company_name }}</div>
                @endif
            </td>
            <td width="45%">
                <div class="doc-title">{{ $isSaleReceipt ? 'PAYMENT RECEIPT' : 'INVOICE' }}</div>
                <div class="meta">
                    <strong>{{ $invoice->invoice_number }}</strong><br>
                    Date: {{ $invoice->issued_at->format('d M Y') }}
                    @if($invoice->due_date && ! $isSaleReceipt)
                        <br>Due: {{ $invoice->due_date->format('d M Y') }}
                    @endif
                    @if($invoice->isVoid())
                        <br><span class="void">VOID</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <table class="bill">
            <tr>
                <td width="100%">
                    <div class="label">Bill To</div>
                    <strong>{{ $invoice->client_name }}</strong><br>
                    @if($invoice->client_email)
                        {{ $invoice->client_email }}<br>
                    @endif
                    @if($invoice->client_phone)
                        {{ $invoice->client_phone }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                @if($showLineDetails)
                    <th class="num" width="70">Qty</th>
                    <th class="num" width="90">Rate</th>
                    @if($showTax)
                        <th class="num" width="70">Tax</th>
                    @endif
                @endif
                <th class="num" width="110">Amount</th>
            </tr>
        </thead>
        <tbody>
            @if($hasItems)
                @foreach($invoice->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->description }}</strong>
                            @if($item->long_description)
                                <div class="muted small">{{ $item->long_description }}</div>
                            @endif
                        </td>
                        @if($showLineDetails)
                            <td class="num">{{ rtrim(rtrim(number_format($item->qty, 2), '0'), '.') }}</td>
                            <td class="num">${{ number_format($item->rate, 2) }}</td>
                            @if($showTax)
                                <td class="num">{{ number_format($item->tax_rate, 2) }}%</td>
                            @endif
                        @endif
                        <td class="num">${{ number_format($item->amount, 2) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td>
                        <strong>{{ $invoice->title }}</strong>
                        @if($invoice->notes)
                            <div class="muted small">{{ $invoice->notes }}</div>
                        @endif
                    </td>
                    <td class="num">${{ number_format($invoice->amount, 2) }}</td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            @if((float) $invoice->discount > 0)
                <tr>
                    <td class="num" colspan="{{ $labelColspan }}">Discount</td>
                    <td class="num">-${{ number_format($invoice->discount, 2) }}</td>
                </tr>
            @endif
            @if((float) $invoice->adjustment != 0)
                <tr>
                    <td class="num" colspan="{{ $labelColspan }}">Adjustment</td>
                    <td class="num">${{ number_format($invoice->adjustment, 2) }}</td>
                </tr>
            @endif
            <tr>
                <th class="num" colspan="{{ $labelColspan }}">{{ $isSaleReceipt ? 'Amount Received' : 'Invoice Amount' }}</th>
                <th class="num">${{ number_format($invoice->amount, 2) }} {{ $invoice->currency }}</th>
            </tr>
            @if(! $isSaleReceipt)
                <tr>
                    <td class="num" colspan="{{ $labelColspan }}">Amount Paid</td>
                    <td class="num">${{ number_format($invoice->amountPaid(), 2) }}</td>
                </tr>
                <tr>
                    <th class="num" colspan="{{ $labelColspan }}">Balance Due</th>
                    <th class="num">${{ number_format($invoice->amountDue(), 2) }}</th>
                </tr>
            @endif
        </tfoot>
    </table>

    @if(! $isSaleReceipt && $invoice->payments->isNotEmpty())
        <div class="section" style="margin-top:18px">
            <div class="label">Payment History</div>
            <table class="items">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>Note</th>
                        <th class="num" width="110">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->payments->sortBy('paid_at') as $payment)
                        <tr>
                            <td>{{ $payment->paid_at?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $payment->payment_mode ?: '—' }}</td>
                            <td class="muted">{{ $payment->note ?: '—' }}</td>
                            <td class="num">${{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($invoice->client_note)
        <p class="note"><strong>Client note:</strong> {{ $invoice->client_note }}</p>
    @endif
    @if($invoice->terms)
        <p class="note"><strong>Terms:</strong> {{ $invoice->terms }}</p>
    @endif
</body>
</html>
