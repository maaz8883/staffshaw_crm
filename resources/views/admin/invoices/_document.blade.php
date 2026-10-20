{{--
    Client-facing invoice / payment receipt. One template for the screen, print and the PDF
    (dompdf: tables + CSS 2.1 only — no flex / grid). Shows only the brand, never the internal
    company, team or agent.
--}}
@php
    $forPdf = $forPdf ?? false;
    $isReceipt = (bool) $invoice->sale_id;
    $hasItems = $invoice->items->isNotEmpty();
    $showLineDetails = $hasItems && ! $isReceipt;
    $showTax = $showLineDetails && $invoice->items->contains(fn ($item) => (float) $item->tax_rate > 0);
    $cur = $invoice->currency ?: 'USD';
    $money = fn ($v) => '$'.number_format((float) $v, 2);

    $status = $invoice->isVoid() ? 'Void' : ($isReceipt ? 'Paid' : $invoice->statusLabel());
    $statusClass = match ($status) {
        'Paid' => 'paid', 'Partially Paid' => 'partial', 'Overdue', 'Unpaid' => 'due', default => 'neutral',
    };

    $subtotal = $hasItems ? (float) $invoice->items->sum('amount') : (float) $invoice->amount;
    $website = $invoice->brand?->website;
    $websiteLabel = $website ? preg_replace('#^https?://(www\.)?#i', '', rtrim($website, '/')) : null;

    $logoSrc = null;
    // Header band colour follows the logo so every logo reads well:
    // light band for normal logos, navy for white logos, slate for black + white logos
    $band = match ($invoice->brand?->logoBackground()) { 'dark' => 'dark', 'mid' => 'mid', default => 'light' };
    $bandRgb = ['light' => [241, 245, 249], 'dark' => [15, 23, 42], 'mid' => [100, 116, 139]][$band];
    if ($invoice->brand?->image) {
        // Margins trimmed so every logo shows at a proper size
        $logoRel = $invoice->brand->trimmedLogo() ?? $invoice->brand->image;
        $path = public_path('storage/'.$logoRel);
        if ($forPdf) {
            // dompdf can't read WEBP and sends transparent PNGs through Imagick (often broken on
            // servers): flatten the logo onto the band colour as a JPEG with GD
            $img = is_file($path) && function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($path)) : false;
            if ($img) {
                $w = imagesx($img);
                $h = imagesy($img);
                $flat = imagecreatetruecolor($w, $h);
                imagefill($flat, 0, 0, imagecolorallocate($flat, ...$bandRgb));
                imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);
                ob_start();
                imagejpeg($flat, null, 92);
                $logoSrc = 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
                imagedestroy($img);
                imagedestroy($flat);
            }
        } else {
            $logoSrc = asset('storage/'.$logoRel);
        }
    }
@endphp
<style>
    .inv-doc { background: #fff; color: #1f2937; font-size: 13px; line-height: 1.5; position: relative; }
    .inv-doc table { width: 100%; border-collapse: collapse; }
    .inv-doc td, .inv-doc th { vertical-align: top; padding: 0; border: 0; }
    .inv-doc .inv-pad { padding: 34px 40px 30px; }
    /* Branded header band */
    .inv-doc .inv-band { padding: 30px 40px; }
    .inv-doc .inv-band td { vertical-align: middle; }
    .inv-doc .inv-band.light { background: #f1f5f9; border-bottom: 1px solid #e2e8f0; }
    .inv-doc .inv-band.dark { background: #0f172a; }
    .inv-doc .inv-band.mid { background: #64748b; }
    .inv-doc .inv-logo { max-height: 72px; max-width: 260px; display: block; }
    .inv-doc .inv-band .inv-site { margin-top: 10px; }
    .inv-doc .inv-band.dark .inv-title, .inv-doc .inv-band.mid .inv-title, .inv-doc .inv-band.dark .inv-brand, .inv-doc .inv-band.mid .inv-brand { color: #fff; }
    .inv-doc .inv-band.dark .inv-site, .inv-doc .inv-band.dark .inv-number { color: #94a3b8; }
    .inv-doc .inv-band.mid .inv-site, .inv-doc .inv-band.mid .inv-number { color: #e2e8f0; }
    .inv-doc .inv-brand { font-size: 22px; font-weight: 700; color: #111827; letter-spacing: -.01em; }
    .inv-doc .inv-site { font-size: 12px; color: #6b7280; margin-top: 6px; }
    .inv-doc .inv-title { font-size: 28px; font-weight: 700; letter-spacing: .16em; color: #0f172a; text-align: right; line-height: 1.1; }
    .inv-doc .inv-number { text-align: right; color: #6b7280; font-size: 12px; margin-top: 6px; }
    .inv-doc .inv-pill { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; margin-top: 10px; }
    .inv-doc .inv-pill.paid { background: #dcfce7; color: #166534; }
    .inv-doc .inv-pill.partial { background: #fef3c7; color: #92400e; }
    .inv-doc .inv-pill.due { background: #fee2e2; color: #991b1b; }
    .inv-doc .inv-pill.neutral { background: #e5e7eb; color: #374151; }
    .inv-doc .inv-label { font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #9ca3af; margin-bottom: 6px; }
    .inv-doc .inv-client { font-size: 15px; font-weight: 700; color: #111827; }
    .inv-doc .inv-muted { color: #6b7280; }
    .inv-doc .inv-meta td { padding: 3px 0; font-size: 12px; }
    .inv-doc .inv-meta .k { color: #6b7280; padding-right: 18px; white-space: nowrap; }
    .inv-doc .inv-meta .v { text-align: right; font-weight: 600; color: #111827; white-space: nowrap; }
    .inv-doc .inv-items { margin-top: 28px; }
    .inv-doc .inv-items th { background: #f3f4f6; color: #4b5563; font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; padding: 10px 14px; text-align: left; }
    .inv-doc .inv-items td { padding: 14px; border-bottom: 1px solid #f0f1f3; }
    .inv-doc .inv-items .num { text-align: right; white-space: nowrap; }
    .inv-doc .inv-items .desc { font-weight: 600; color: #111827; }
    .inv-doc .inv-items .sub { font-size: 12px; color: #6b7280; margin-top: 3px; }
    .inv-doc .inv-totals { width: 46%; margin-left: 54%; margin-top: 18px; }
    .inv-doc .inv-totals td { padding: 6px 14px; font-size: 13px; }
    .inv-doc .inv-totals .k { color: #6b7280; }
    .inv-doc .inv-totals .v { text-align: right; white-space: nowrap; color: #111827; }
    .inv-doc .inv-totals .grand td { background: #111827; color: #fff; font-size: 15px; font-weight: 700; padding: 12px 14px; }
    .inv-doc .inv-totals .grand .v { color: #fff; }
    .inv-doc .inv-totals .due td { font-weight: 700; color: #991b1b; padding-top: 10px; }
    .inv-doc .inv-summary { margin-top: 26px; background: #f9fafb; border: 1px solid #eef0f2; }
    .inv-doc .inv-summary td { padding: 14px 18px; width: 33.33%; }
    .inv-doc .inv-summary .amt { font-size: 16px; font-weight: 700; color: #111827; margin-top: 2px; }
    .inv-doc .inv-summary .amt.ok { color: #166534; }
    .inv-doc .inv-notes { margin-top: 26px; font-size: 12px; color: #4b5563; }
    .inv-doc .inv-notes p { margin: 0 0 4px; }
    .inv-doc .inv-void { border: 2px solid #dc2626; color: #dc2626; display: inline-block; padding: 4px 14px; font-weight: 700; letter-spacing: .2em; font-size: 13px; margin-top: 10px; }
    .inv-doc .inv-foot { margin-top: 34px; padding-top: 18px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 12px; color: #6b7280; }
    .inv-doc .inv-foot strong { color: #111827; font-size: 13px; }
    .inv-doc a { color: inherit; text-decoration: none; }
    @media print { .inv-doc { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>

<div class="inv-doc">
    {{-- Brand header band --}}
    <div class="inv-band {{ $band }}">
        <table>
            <tr>
                <td style="width:60%">
                    @if($logoSrc)
                        <img class="inv-logo" src="{{ $logoSrc }}" alt="{{ $invoice->brand_name }}">
                    @else
                        <div class="inv-brand">{{ $invoice->brand_name ?: 'Invoice' }}</div>
                    @endif
                    @if($websiteLabel)
                        <div class="inv-site">{{ $websiteLabel }}</div>
                    @endif
                </td>
                <td style="width:40%; text-align:right">
                    <div class="inv-title">{{ $isReceipt ? 'RECEIPT' : 'INVOICE' }}</div>
                    @if($invoice->isVoid())
                        <div class="inv-void">VOID</div>
                    @else
                        <span class="inv-pill {{ $statusClass }}">{{ $status }}</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="inv-pad">

        {{-- Billed to + key dates --}}
        <table>
            <tr>
                <td style="width:55%">
                    <div class="inv-label">{{ $isReceipt ? 'Received from' : 'Billed to' }}</div>
                    <div class="inv-client">{{ $invoice->client_name }}</div>
                    @if($invoice->client_email)<div class="inv-muted">{{ $invoice->client_email }}</div>@endif
                    @if($invoice->client_phone)<div class="inv-muted">{{ $invoice->client_phone }}</div>@endif
                </td>
                <td style="width:45%">
                    <table class="inv-meta">
                        <tr><td class="k">{{ $isReceipt ? 'Receipt no.' : 'Invoice no.' }}</td><td class="v">{{ $invoice->invoice_number }}</td></tr>
                        <tr><td class="k">{{ $isReceipt ? 'Payment date' : 'Issue date' }}</td><td class="v">{{ $invoice->issued_at->format('M d, Y') }}</td></tr>
                        @if($invoice->due_date && ! $isReceipt)
                            <tr><td class="k">Due date</td><td class="v">{{ $invoice->due_date->format('M d, Y') }}</td></tr>
                        @endif
                        <tr><td class="k">{{ $isReceipt ? 'Amount paid' : 'Amount due' }}</td>
                            <td class="v">{{ $money($isReceipt ? $invoice->amount : $invoice->amountDue()) }} {{ $cur }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- Line items --}}
        <table class="inv-items">
            <thead>
                <tr>
                    <th>Description</th>
                    @if($showLineDetails)
                        <th class="num" style="width:60px">Qty</th>
                        <th class="num" style="width:100px">Rate</th>
                        @if($showTax)<th class="num" style="width:70px">Tax</th>@endif
                    @endif
                    <th class="num" style="width:120px">Amount</th>
                </tr>
            </thead>
            <tbody>
                @if($hasItems)
                    @foreach($invoice->items as $item)
                        <tr>
                            <td>
                                <div class="desc">{{ $item->description }}</div>
                                @if($item->long_description)<div class="sub">{{ $item->long_description }}</div>@endif
                            </td>
                            @if($showLineDetails)
                                <td class="num">{{ rtrim(rtrim(number_format($item->qty, 2), '0'), '.') }}</td>
                                <td class="num">{{ $money($item->rate) }}</td>
                                @if($showTax)<td class="num">{{ rtrim(rtrim(number_format($item->tax_rate, 2), '0'), '.') }}%</td>@endif
                            @endif
                            <td class="num">{{ $money($item->amount) }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td>
                            <div class="desc">{{ $invoice->title ?: 'Payment' }}</div>
                            @if($invoice->notes)<div class="sub">{!! nl2br(e($invoice->notes)) !!}</div>@endif
                        </td>
                        <td class="num">{{ $money($invoice->amount) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        {{-- Totals --}}
        <table class="inv-totals">
            @if((float) $invoice->discount > 0 || (float) $invoice->adjustment != 0)
                <tr><td class="k">Subtotal</td><td class="v">{{ $money($subtotal) }}</td></tr>
            @endif
            @if((float) $invoice->discount > 0)
                <tr><td class="k">Discount</td><td class="v">−{{ $money($invoice->discount) }}</td></tr>
            @endif
            @if((float) $invoice->adjustment != 0)
                <tr><td class="k">Adjustment</td><td class="v">{{ $money($invoice->adjustment) }}</td></tr>
            @endif
            @if((float) $invoice->tax_total > 0)
                <tr><td class="k">Tax (included)</td><td class="v">{{ $money($invoice->tax_total) }}</td></tr>
            @endif
            <tr class="grand">
                <td class="k">{{ $isReceipt ? 'Amount received' : 'Total' }}</td>
                <td class="v">{{ $money($invoice->amount) }} {{ $cur }}</td>
            </tr>
            @if(! $isReceipt)
                <tr><td class="k" style="padding-top:10px">Paid</td><td class="v" style="padding-top:10px">{{ $money($invoice->amountPaid()) }}</td></tr>
                <tr class="{{ $invoice->amountDue() > 0 ? 'due' : '' }}"><td class="k">Balance due</td><td class="v">{{ $money($invoice->amountDue()) }}</td></tr>
            @endif
        </table>

        {{-- Receipt: where the whole project stands after this payment --}}
        @if($isReceipt && (float) $invoice->sale_total > 0)
            <table class="inv-summary">
                <tr>
                    <td><div class="inv-label">Project total</div><div class="amt">{{ $money($invoice->sale_total) }}</div></td>
                    <td><div class="inv-label">Paid to date</div><div class="amt ok">{{ $money($invoice->sale_received) }}</div></td>
                    <td><div class="inv-label">Remaining balance</div><div class="amt">{{ $money($invoice->sale_balance) }}</div></td>
                </tr>
            </table>
        @endif

        @if(! $isReceipt && $invoice->payments->isNotEmpty())
            <table class="inv-items" style="margin-top:26px">
                <thead><tr><th>Payment date</th><th>Method</th><th>Note</th><th class="num" style="width:120px">Amount</th></tr></thead>
                <tbody>
                    @foreach($invoice->payments->sortBy('paid_at') as $payment)
                        <tr>
                            <td>{{ $payment->paid_at?->format('M d, Y') ?? '—' }}</td>
                            <td>{{ $payment->payment_mode ?: '—' }}</td>
                            <td class="inv-muted">{{ $payment->note ?: '—' }}</td>
                            <td class="num">{{ $money($payment->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($invoice->client_note || $invoice->terms)
            <div class="inv-notes">
                @if($invoice->client_note)<div class="inv-label">Notes</div><p>{!! nl2br(e($invoice->client_note)) !!}</p>@endif
                @if($invoice->terms)<div class="inv-label" style="margin-top:12px">Terms &amp; conditions</div><p>{!! nl2br(e($invoice->terms)) !!}</p>@endif
            </div>
        @endif

        <div class="inv-foot">
            <strong>Thank you for your business!</strong><br>
            {{ $invoice->brand_name }}@if($websiteLabel) · {{ $websiteLabel }}@endif
        </div>
    </div>
</div>
